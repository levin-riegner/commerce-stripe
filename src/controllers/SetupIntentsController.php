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
        $return_url = $request->getRequiredBodyParam('return_url');

        $gateway = Commerce::getInstance()->getGateways()->getGatewayById((int)$gatewayId);

        try {
            if (!$gateway || !$gateway instanceof SetupPaymentIntents) {
                throw new BadRequestHttpException('That is not a valid gateway id.');
            }
            $setupIntent = $gateway->createSetupIntent(Craft::$app->getUser()->id, $paymentMethodId, $return_url);
            if ($request->getAcceptsJson())
                return $this->asJson($setupIntent);
            else{
                //3DS validation required
                if($setupIntent['setupIntent']->status == "requires_action"){
                    $nextAction = $setupIntent['setupIntent']->next_action->toArray();
                    return $this->redirect($nextAction['redirect_to_url']['url']);
                }
            }
            
            //No validation required
            return $this->redirectToPostedUrl();

        } catch (\Throwable $e) {
            Craft::dd($e);
            if ($request->getAcceptsJson())
                return $this->asErrorJson($e->getMessage());
            
            Craft::$app->getUrlManager()->setRouteParams(['error' => $e->getMessage()]);

            return null;
        }
    }

    public function actionConfirm()
    {
        $request = Craft::$app->getRequest();
        $return_url = $request->getQueryParam('return_url');
        $setupIntent = $request->getQueryParam("setup_intent");
        //setup_intent=seti_1HaNY8EQOpsFr3kowZtYGwzO
        try {
            $gateway = Commerce::getInstance()->getGateways()->getGatewayByHandle('stripe');
            if (!$gateway || !$gateway instanceof SetupPaymentIntents) {
                throw new BadRequestHttpException('That is not a valid gateway id.');
            }
            if(!$setupIntent) 
                $setupIntent = [];
            else 
                $setupIntent = $gateway->saveSetupIntent(Craft::$app->getUser()->id, $setupIntent);
            if ($request->getAcceptsJson())
                return $this->asJson($setupIntent);
            else
                return $this->redirect($return_url);
        } catch (\Throwable $e) {
            if ($request->getAcceptsJson())
                return $this->asErrorJson($e->getMessage());
            
            Craft::$app->getUrlManager()->setRouteParams(['error' => $e->getMessage()]);

            return null;
        }
    }
}
