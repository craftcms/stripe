<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Callout;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\MarkdownContent;
use CraftCms\Cms\Ui\Nodes\TemplateContent;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Webhooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Receives Stripe webhook events, and manages the webhook endpoint.
 *
 * @since 3.0.0
 */
class WebhooksController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly Api $api,
        private readonly Webhooks $webhooks,
    ) {}

    /**
     * Verifies a webhook event’s signature, and syncs the object it’s about.
     */
    public function handle(Request $request): Response
    {
        $secret = $this->api->getWebhookSigningSecret();

        if ($secret === null) {
            Log::error('Received a Stripe webhook event, but no webhook signing secret is set.');

            return response('Err', 400);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            Log::error("Stripe webhook handler failed: {$e->getMessage()}");

            return response('Err', 400);
        }

        // Stripe asks for a quick response, so process the event after sending it
        app()->terminating(fn () => $this->webhooks->processEvent($event));

        return response('OK');
    }

    public function index(): CpScreenResponse
    {
        $response = new CpScreenResponse()
            ->title(t('Webhooks', category: 'stripe'))
            ->selectedSubnavItem('stripe/webhooks');

        if (! $this->api->isConfigured()) {
            return $response->ui(Ui::make([
                Callout::make('not-configured', t('Set a Stripe secret key in the plugin settings first.', category: 'stripe'))->variant('warning'),
            ]));
        }

        $webhook = $this->webhooks->getWebhook();
        $endpoint = $this->webhooks->fetchEndpoint();

        $nodes = [];

        if ($endpoint !== null) {
            $nodes[] = Callout::make('registered', t('Stripe sends events to {url} ({status}).', [
                'url' => $endpoint->url,
                'status' => $endpoint->status,
            ], 'stripe'))->variant('success');
        } else {
            $nodes[] = MarkdownContent::make('instructions', t('No webhook endpoint is registered. Register one for {url}, or create one in the Stripe Dashboard and enter its ID and signing secret below.', [
                'url' => '`'.Url::siteUrl('stripe/webhooks/handle').'`',
            ], 'stripe'));
            $nodes[] = TemplateContent::make('register', $this->actionButton('stripe/webhooks/create', t('Register webhook', category: 'stripe')))->trusted();
        }

        $nodes[] = Field::make(t('Webhook ID', category: 'stripe'), Text::make('webhookId')->monospace())
            ->instructions(t('The ID of the webhook endpoint in Stripe, or an environment variable that holds it.', category: 'stripe'));
        $nodes[] = Field::make(t('Webhook Signing Secret', category: 'stripe'), Text::make('webhookSigningSecret')->monospace())
            ->instructions(t('The endpoint’s signing secret, or an environment variable that holds it.', category: 'stripe'))
            ->required();

        if ($webhook !== null) {
            $nodes[] = TemplateContent::make('delete', $this->actionButton('stripe/webhooks/delete', t('Delete webhook', category: 'stripe'), t('Are you sure you want to delete the webhook endpoint in Stripe?', category: 'stripe')))->trusted();
        }

        return $response->ui(Ui::make($nodes), new UiContext(values: [
            'webhookId' => $webhook->webhookId ?? '',
            'webhookSigningSecret' => $webhook->webhookSigningSecret ?? '',
        ]), [
            'submit' => ['method' => 'post', 'url' => Url::actionUrl('stripe/webhooks/save-settings')],
        ]);
    }

    /**
     * A form with a single button that posts to an action.
     */
    private function actionButton(string $action, string $label, ?string $confirm = null): string
    {
        return Html::beginForm(Url::actionUrl($action), 'post', array_filter([
            'onsubmit' => $confirm ? 'return confirm('.Json::encode($confirm).')' : null,
        ])).
            Html::csrfInput().
            Html::button($label, ['type' => 'submit', 'class' => 'btn']).
            Html::endForm();
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'webhookId' => ['nullable', 'string'],
            'webhookSigningSecret' => ['required', 'string'],
        ]);

        $this->webhooks->saveWebhook($data['webhookId'] ?? null, $data['webhookSigningSecret']);

        return $this->asSuccess(t('Webhook settings saved.', category: 'stripe'));
    }

    public function create(): Response
    {
        try {
            $this->webhooks->registerEndpoint(Url::siteUrl('stripe/webhooks/handle'));
        } catch (Throwable $e) {
            Log::error("Unable to create Stripe webhook: {$e->getMessage()}");

            return $this->asFailure(t('Unable to create webhook', category: 'stripe'));
        }

        return $this->asSuccess(t('Webhook registered.', category: 'stripe'));
    }

    public function destroy(): Response
    {
        try {
            $this->webhooks->deleteEndpoint();
        } catch (Throwable $e) {
            Log::error("Stripe webhook could not be deleted: {$e->getMessage()}");

            return $this->asFailure(t('Webhook could not be deleted', category: 'stripe'));
        }

        return $this->asSuccess(t('Webhook deleted', category: 'stripe'));
    }
}
