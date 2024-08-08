<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\stripe\models\Settings;
use craft\stripe\Plugin;
use craft\web\Controller;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;
use Stripe\WebhookEndpoint;
use yii\base\InvalidConfigException;
use yii\web\Response as YiiResponse;
use yii\web\ServerErrorHttpException;

/**
 * The WebhooksController handles Stripe webhook event.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class WebhooksController extends Controller
{
    public $defaultAction = 'handle';
    public array|bool|int $allowAnonymous = ['handle'];

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Disable CSRF only for incoming webhooks (to agree with `allowAnonymous`, above):
        if ($action->id === 'handle') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Handle incoming Stripe webhook event
     *
     * @return YiiResponse
     */
    public function actionHandle(): YiiResponse
    {
        $apiService = Plugin::getInstance()->getApi();
        $webhookService = Plugin::getInstance()->getWebhooks();
        $webhookSigningSecret = $apiService->getWebhookSigningSecret();

        // verify
        $payload = @file_get_contents('php://input');
        $signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];

        try {
            $event = Webhook::constructEvent(
                $payload, $signatureHeader, $webhookSigningSecret
            );
        } catch (UnexpectedValueException $e) {
            Craft::error("Stripe webhook handler failed with message:" . $e->getMessage());
            // Invalid payload
            $this->response->setStatusCode(400);
            return $this->asRaw('Err');
        } catch (SignatureVerificationException $e) {
            Craft::error("Stripe webhook handler failed with message:" . $e->getMessage());
            // Invalid signature
            $this->response->setStatusCode(400);
            return $this->asRaw('Err');
        }

        $this->response->setStatusCode(200);
        // as per https://docs.stripe.com/webhooks#acknowledge-events-immediately - send response asap
        $this->response->sendAndClose();

        // Handle the event
        $webhookService->processEvent($event);

        // leaving this in even after sendAndClose() so that the response type matches
        return $this->asRaw('OK');
    }

    /**
     * Edit page for the webhook management
     *
     * @return YiiResponse
     */
    public function actionEdit(): YiiResponse
    {
        $plugin = Plugin::getInstance();
        $pluginSettings = $plugin->getSettings();

        if (!$pluginSettings->secretKey) {
            throw new ServerErrorHttpException('No Stripe API key found. Make sure you have added one in the plugin’s settings screen.');
        }

        $webhookInfo = [];
        $hasWebhook = true;
        $webhookId = App::parseEnv($pluginSettings->webhookId);

        if (!empty($webhookId)) {
            try {
                $response = $this->getWebhookInfo($plugin, $webhookId);
                $webhookInfo = $response->toArray();
            } catch (\Exception $e) {
                Craft::error("Couldn't retrieve webhook with ID $webhookId: " . $e->getMessage());
                $hasWebhook = false;
            }
        } else {
            $hasWebhook = false;
        }

        return $this->renderTemplate('stripe/webhooks/_index', [
            'webhookInfo' => $webhookInfo,
            'hasWebhook' => $hasWebhook,
        ]);
    }

    /**
     * Creates webhook endpoint in Stripe Dashboard and saves the corresponding Webhook Signing Secret in the plugin's settings.
     *
     * @return YiiResponse
     * @throws InvalidConfigException
     * @throws \Stripe\Exception\ApiErrorException
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\MethodNotAllowedHttpException
     */
    public function actionCreate()
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();
        $pluginSettings = $plugin->getSettings();

        if (!$pluginSettings->secretKey) {
            throw new ServerErrorHttpException('No Stripe API key found, check credentials in settings.');
        }

        $stripe = $plugin->getApi()->getClient();
        try {
            $response = $stripe->webhookEndpoints->create([
                'enabled_events' => [
                    'product.created',
                    'product.updated',
                    'product.deleted',
                    'price.created',
                    'price.updated',
                    'price.deleted',
                    'customer.subscription.created',
                    'customer.subscription.updated',
                    'customer.subscription.paused',
                    'customer.subscription.resumed',
                    'customer.subscription.pending_update_applied',
                    'customer.subscription.pending_update_expired',
                    'customer.subscription.deleted',
                    'customer.created',
                    'customer.deleted',
                    'payment_method.attached',
                    'payment_method.automatically_updated',
                    'payment_method.updated',
                    'payment_method.detached',
                    'invoice.created',
                    'invoice.finalized',
                    'invoice.marked_uncollectible',
                    'invoice.overdue',
                    'invoice.paid',
                    'invoice.payment_action_required',
                    'invoice.payment_failed',
                    'invoice.payment_succeeded',
                    'invoice.updated',
                    'invoice.voided',
                    'invoice.deleted',
                ],
                'url' => UrlHelper::siteUrl('/stripe/webhooks/handle'),
                'api_version' => $plugin->getApi()::STRIPE_API_VERSION,
            ]);
        } catch (\Exception $e) {
            Craft::error("Unable to create webhook: " . $e->getMessage());
            $this->setFailFlash(Craft::t('stripe', 'Unable to create webhook'));
            return $this->redirectToPostedUrl();
        }

        // get the webhook signing secret
        $this->saveWebhookData($plugin, $pluginSettings, $response);

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes a webhook from the Stripe API.
     *
     * @return YiiResponse
     */
    public function actionDelete(): YiiResponse
    {
        $this->requireAcceptsJson();
        $id = Craft::$app->getRequest()->getBodyParam('id');

        $stripe = Plugin::getInstance()->getApi()->getClient();

        try {
            $stripe->webhookEndpoints->delete($id);
        } catch (\Exception $e) {
            Craft::error('Webhook could not be deleted: ' . $e->getMessage());
            return $this->asFailure(Craft::t('stripe', 'Webhook could not be deleted'));
        }

        return $this->asSuccess(Craft::t('stripe', 'Webhook deleted'));
    }

    /**
     * Saves the webhook id and signing secret in the .env file and/or database and update plugin's settings.
     * Sets the relevant flash message.
     *
     * @param Plugin $plugin
     * @param Settings $settings
     * @param WebhookEndpoint $response
     * @return void
     */
    private function saveWebhookData(Plugin $plugin, Settings $settings, WebhookEndpoint $response): void
    {
        $configService = Craft::$app->getConfig();

        $success = true;
        try {
            $configService->setDotEnvVar('STRIPE_WH_KEY', $response->secret ?? '');
        } catch (\Throwable $e) {
            $success = false;
            Craft::error('Couldn\'t save the Stripe Webhook Signing Secret in the .env file. ' . $e->getMessage());
        }
        $success ? $settings->webhookSigningSecret = '$STRIPE_WH_KEY' : $response->secret;

        $success = true;
        try {
            $configService->setDotEnvVar('STRIPE_WH_ID', $response->id ?? '');
        } catch (\Throwable $e) {
            $success = false;
            Craft::error('Couldn\'t save the Stripe Webhook ID in the .env file. ' . $e->getMessage());
        }
        $success ? $settings->webhookId = '$STRIPE_WH_ID' : $response->id;

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            Craft::$app->getSession()->setNotice(Craft::t(
                'stripe',
                'Webhook registered successfully, but we had trouble saving the Webhook Signing Secret. Please go to your Stripe Dashboard, get the webhook signing secret and add it to your plugin’s settings.')
            );
        } else {
            $this->setSuccessFlash(Craft::t(
                'stripe',
                'Webhook registered.')
            );
        }
    }

    /**
     * Returns Webhook Endpoint info from Stripe.
     *
     * @param Plugin $plugin
     * @param string $webhookId
     * @return WebhookEndpoint
     * @throws \Stripe\Exception\ApiErrorException
     */
    private function getWebhookInfo(Plugin $plugin, string $webhookId): WebhookEndpoint
    {
        $stripe = $plugin->getApi()->getClient();
        return $stripe->webhookEndpoints->retrieve($webhookId);
    }
}
