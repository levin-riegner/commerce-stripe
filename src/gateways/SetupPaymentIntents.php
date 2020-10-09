<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\stripe\gateways;

use Craft;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\stripe\Plugin;
use Stripe\SetupIntent;
use Stripe\PaymentMethod;

/**
 * This class represents the Stripe Payment Intents gateway
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 **/
class SetupPaymentIntents extends PaymentIntents
{
    public function createSetupIntent($userId, $pmToken){
        
        $stripeCustomer = $this->getStripeCustomer($userId);

        $setupIntent = SetupIntent::create([
            'customer' => $stripeCustomer->id,
            'payment_method' => $pmToken,
            'confirm' => true
        ]);

        $paymentForm = new CreditCardPaymentForm();
        $paymentForm->paymentMethodId = $pmToken;
        
        //Remove sensitive information
        unset($setupIntent['client_secret']);

        Plugin::getInstance()->getPaymentSources()->createPaymentSource($userId, $this, $paymentForm);

        return $setupIntent;
    }
}
