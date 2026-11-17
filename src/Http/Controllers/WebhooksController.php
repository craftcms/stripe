<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Cp\Components\Button;
use CraftCms\Cms\Cp\Html\StatusHtml;
use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Callout;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\MarkdownContent;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Webhooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
 * @since 2.0.0
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

        $nodes[] = Table::make('webhook-endpoint')
            ->columns([
                ['key' => 'topic', 'label' => t('Topic', category: 'stripe')],
                ['key' => 'apiVersion', 'label' => t('API Version', category: 'stripe')],
                ['key' => 'livemode', 'label' => t('Live Mode', category: 'stripe')],
                ['key' => 'status', 'label' => t('Status', category: 'stripe')],
                ['key' => 'url', 'label' => t('URL', category: 'stripe')],
            ])
            ->rows($endpoint !== null ? [[
                'topic' => (string) ($endpoint->description ?: $endpoint->id),
                'apiVersion' => (string) $endpoint->api_version,
                'livemode' => $endpoint->livemode ? t('Yes') : t('No'),
                'status' => ['html' => app(StatusHtml::class)->statusLabelHtml([
                    'color' => $endpoint->status === 'enabled' ? Color::Green : Color::Red,
                    'label' => t(ucfirst((string) $endpoint->status), category: 'stripe'),
                ]) ?? ''],
                'url' => (string) $endpoint->url,
            ]] : [])
            ->emptyMessage(t('No webhooks exist yet.', category: 'stripe'));

        if ($endpoint === null) {
            $nodes[] = MarkdownContent::make('instructions', t('No webhook endpoint is registered. Register one for {url}, or create one in the Stripe Dashboard and enter its ID and signing secret below.', [
                'url' => '`'.Url::siteUrl('stripe/webhooks/handle').'`',
            ], 'stripe'));
            $response->additionalButtonsHtml(Button::make()
                ->label(t('Register webhook', category: 'stripe'))
                ->icon('plus')
                ->action($this->postAction('stripe/webhooks/create')));
        }

        $envTriggers = SelectOptions::getEnvTextExpanderTriggers(true);

        $nodes[] = Field::make(t('Webhook ID', category: 'stripe'), Text::make('webhookId')->monospace()->textExpanderTriggers($envTriggers))
            ->instructions(t('The ID of the webhook endpoint in Stripe, or an environment variable that holds it.', category: 'stripe'));
        $nodes[] = Field::make(t('Webhook Signing Secret', category: 'stripe'), Text::make('webhookSigningSecret')->monospace()->textExpanderTriggers($envTriggers))
            ->instructions(t('The endpoint’s signing secret, or an environment variable that holds it.', category: 'stripe'))
            ->required();

        if ($webhook !== null) {
            $response->addAltAction(t('Delete webhook', category: 'stripe'), [
                'variant' => 'danger',
                'action' => $this->postAction('stripe/webhooks/delete', t('Are you sure you want to delete the webhook endpoint in Stripe?', category: 'stripe')),
            ]);
        }

        return $response->ui(Ui::make($nodes), new UiContext(values: [
            'webhookId' => $webhook->webhookId ?? '',
            'webhookSigningSecret' => $webhook->webhookSigningSecret ?? '',
        ]), [
            'submit' => ['method' => 'post', 'url' => Url::actionUrl('stripe/webhooks/save-settings')],
        ]);
    }

    /**
     * An action that posts to a controller action, then returns to the Webhooks screen.
     *
     * @return array<string, mixed>
     */
    private function postAction(string $action, ?string $confirm = null): array
    {
        return array_filter([
            'type' => 'http',
            'method' => 'POST',
            'url' => Url::actionUrl($action),
            'body' => ['redirect' => Crypt::encrypt(Url::cpUrl('stripe/webhooks'))],
            'confirm' => $confirm,
        ]);
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
