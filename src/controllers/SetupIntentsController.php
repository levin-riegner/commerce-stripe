<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\stripe\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\commerce\stripe\base\SubscriptionGateway;
use craft\commerce\stripe\gateways\SetupPaymentIntents;
use craft\web\Controller as BaseController;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * This controller provides functionality to handle setup intents
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class SetupIntentsController extends BaseController
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action)
	{

        $this->enableCsrfValidation = false;

		return parent::beforeAction($action);
    }

    public function actionCreate()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $gatewayId = $request->getRequiredBodyParam('gatewayId');
        $paymentMethodId = $request->getRequiredBodyParam('paymentMethodId');

        $gateway = Commerce::getInstance()->getGateways()->getGatewayById((int)$gatewayId);

        try {
            if (!$gateway || !$gateway instanceof SetupPaymentIntents) {
                throw new BadRequestHttpException('That is not a valid gateway id.');
            }

            return $this->asJson($gateway->createSetupIntent(Craft::$app->getUser()->id, $paymentMethodId));
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }
}
