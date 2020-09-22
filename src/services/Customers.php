<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\stripe\services;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\commerce\stripe\gateways\Gateway;
use craft\commerce\stripe\Plugin as StripePlugin;
use craft\commerce\stripe\errors\CustomerException;
use craft\commerce\stripe\events\CreateCustomerEvent;
use craft\commerce\stripe\events\UpdateCustomerEvent;
use craft\commerce\stripe\models\Customer;
use craft\commerce\stripe\records\Customer as CustomerRecord;
use craft\db\Query;
use craft\elements\User;
use Stripe\Customer as StripeCustomer;
use Stripe\Stripe;
use yii\base\Component;
use yii\base\Exception;

/**
 * Customer service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Customers extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event CreateUserEvent The event that is triggered before a user is created on stripe
     *
     * Plugins can get notified whenever a new user is going to be created in stripe, and add custom fields to it
     *
     * ```php
     * use craft\commerce\stripe\events\CreateCustomerEvent;
     * use craft\commerce\stripe\services\Customers;
     * use yii\base\Event;
     *
     * Event::on(Customers::class, Customers::EVENT_BEFORE_CREATE_CUSTOMER, function(CreateUserEvent $e) {
     *     $customerData = $e->customerData;
     *     // Do something with the data...
     * });
     * ```
     */
    const EVENT_BEFORE_CREATE_CUSTOMER = 'beforeCreateCustomer';

    /**
     * @event UpdateUserEvent The event that is triggered before a user is update on stripe
     *
     * Plugins can get notified whenever a new user is going to be updated in stripe, and add custom fields to it
     *
     * ```php
     * use craft\commerce\stripe\events\UpdateCustomerEvent;
     * use craft\commerce\stripe\services\Customers;
     * use yii\base\Event;
     *
     * Event::on(Customers::class, Customers::EVENT_BEFORE_UPDATE_CUSTOMER, function(UpdateCustomerEvent $e) {
     *     // Review current customer information
     *     $customer = $e->customer;
     *     // Do something with the data...
     *     $updatedData = $e->updatedData;
     *     // Triger the update
     *     $e->isValid = true;
     * });
     * ```
     */
    const EVENT_BEFORE_UPDATE_CUSTOMER = 'beforeUpdateCustomer';

    // Public Methods
    // =========================================================================

    /**
     * Returns a customer by gateway and user
     *
     * @param int $gatewayId The stripe gateway
     * @param User $user The user
     *
     * @return Customer
     * @throws CustomerException
     */
    public function getCustomer(int $gatewayId, User $user): Customer
    {
        Stripe::setApiKey(Craft::parseEnv(Commerce::getInstance()->getGateways()->getGatewayById($gatewayId)->apiKey));
        Stripe::setAppInfo(StripePlugin::getInstance()->name, StripePlugin::getInstance()->version, StripePlugin::getInstance()->documentationUrl);
        Stripe::setApiVersion(Gateway::STRIPE_API_VERSION);

        $result = $this->_createCustomerQuery()
            ->where(['userId' => $user->id, 'gatewayId' => $gatewayId])
            ->one();

        $customer = null;

        if ($result !== null) {
            $customer = new Customer($result);

            $event = new UpdateCustomerEvent(['customer' => $customer]);

            if ($this->hasEventHandlers(self::EVENT_BEFORE_UPDATE_CUSTOMER)) {
                $this->trigger(self::EVENT_BEFORE_UPDATE_CUSTOMER, $event);
            }

            //Asume no changes need to be made to the current user if !isValid
            if (!$event->isValid) {
                return $customer;
            }

            $stripeCustomer = StripeCustomer::update($result['reference'], $event->updatedData);

            $customer->response = $stripeCustomer->jsonSerialize();

        }else{
    
            $event = new CreateCustomerEvent([
                'customerData' => [
                    'description' => Craft::t('commerce-stripe', 'Customer for Craft user with ID {id}', ['id' => $user->id]),
                    'email' => $user->email
                ]
            ]);
    
            if ($this->hasEventHandlers(self::EVENT_BEFORE_CREATE_CUSTOMER)) {
                $this->trigger(self::EVENT_BEFORE_CREATE_CUSTOMER, $event);
            }
    
            /** @var StripeCustomer $stripeCustomer */
            $stripeCustomer = StripeCustomer::create($event->customerData);
    
            $customer = new Customer([
                'userId' => $user->id,
                'gatewayId' => $gatewayId,
                'reference' => $stripeCustomer->id,
                'response' => $stripeCustomer->jsonSerialize()
            ]); 
        }

        if (!$this->saveCustomer($customer)) {
            throw new CustomerException('Could not save customer: ' . implode(', ', $customer->getErrorSummary(true)));
        }

        return $customer;
    }

    /**
     * Return a customer by its id.
     *
     * @param int $id
     *
     * @return Customer|null
     */
    public function getCustomerById(int $id) {
        $customerRow = $this->_createCustomerQuery()
            ->where(['id' => $id])
            ->one();

        if ($customerRow) {
            return new Customer($customerRow);
        }

        return null;
    }

    /**
     * Return a customer by its reference.
     *
     * @param string $reference
     *
     * @return Customer|null
     */
    public function getCustomerByReference(string $reference) {
        $customerRow = $this->_createCustomerQuery()
            ->where(['reference' => $reference])
            ->one();

        if ($customerRow) {
            return new Customer($customerRow);
        }

        return null;
    }

    /**
     * Save a customer
     *
     * @param Customer $customer The customer being saved.
     * @return bool Whether the payment source was saved successfully
     * @throws Exception if payment source not found by id.
     */
    public function saveCustomer(Customer $customer): bool
    {
        if ($customer->id) {
            $record = CustomerRecord::findOne($customer->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce-stripe', 'No customer exists with the ID “{id}”',
                    ['id' => $customer->id]));
            }
        } else {
            $record = new CustomerRecord();
        }

        $record->userId = $customer->userId;
        $record->gatewayId = $customer->gatewayId;
        $record->reference = $customer->reference;
        $record->response = $customer->response;

        $customer->validate();
        
        if (!$customer->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $customer->id = $record->id;

            return true;
        }

        return false;
    }

    /**
     * Delete a customer by it's id.
     *
     * @param int $id The id
     *
     * @return bool
     * @throws \Throwable in case something went wrong when deleting.
     */
    public function deleteCustomerById($id): bool
    {
        $record = CustomerRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    // Private methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving customers.
     *
     * @return Query The query object.
     */
    private function _createCustomerQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'gatewayId',
                'userId',
                'reference',
                'response',
            ])
            ->from(['{{%stripe_customers}}']);
    }

}
