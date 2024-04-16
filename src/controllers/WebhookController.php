<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\stripe\Plugin;
use craft\web\Controller;
use craft\web\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;
use yii\web\Response as YiiResponse;


/**
 * The WebhookController handles Stripe webhook event.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class WebhookController extends Controller
{
    public $defaultAction = 'handle';
    public $enableCsrfValidation = false;
    public array|bool|int $allowAnonymous = ['handle'];

    /**
     * Handle incoming Stripe webhook event
     *
     * @return bool|int
     */
    public function actionHandle(): YiiResponse
    {
        $apiService = Plugin::getInstance()->getApi();
        $webhookService = Plugin::getInstance()->getWebhook();
        $client = $apiService->getClient();
        $endpointSecret = $apiService->getEndpointSecret();

        // verify
        $payload = @file_get_contents('php://input');
        $signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = Webhook::constructEvent(
                $payload, $signatureHeader, $endpointSecret
            );
        } catch(UnexpectedValueException $e) {
            Craft::error("Stripe webhook handler failed with message:" . $e->getMessage());
            // Invalid payload
            $this->response->setStatusCode(400);
            return $this->asRaw('Err');
        } catch(SignatureVerificationException $e) {
            Craft::error("Stripe webhook handler failed with message:" . $e->getMessage());
            // Invalid signature
            $this->response->setStatusCode(400);
            return $this->asRaw('Err');
        }

        // Handle the event
        $webhookService->processEvent($event);

        $this->response->setStatusCode(200);
        return $this->asRaw('OK');
    }
}
