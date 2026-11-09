<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements;

use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Enums\ElementActionContext;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\BillingPortal;
use CraftCms\Stripe\Elements\Concerns\HasStripeData;
use CraftCms\Stripe\Elements\Queries\SubscriptionQuery;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Models\Subscription as SubscriptionModel;
use CraftCms\Stripe\Products;
use CraftCms\Stripe\Support\StripeLink;
use DateTime;
use Override;

use function CraftCms\Cms\t;

/**
 * A Stripe subscription, synced from Stripe.
 *
 * @since 2.0.0
 */
class Subscription extends Element
{
    use HasStripeData;

    public const string STATUS_LIVE = 'live';

    public const string STATUS_STRIPE_SCHEDULED = 'stripeScheduled';

    public const string STATUS_STRIPE_CANCELED = 'stripeCanceled';

    public const string STATUS_STRIPE_SUSPENDED = 'stripeSuspended';

    public const string STRIPE_STATUS_ACTIVE = 'active';

    public const string STRIPE_STATUS_TRIALING = 'trialing';

    public const string STRIPE_STATUS_SCHEDULED = 'scheduled';

    public const string STRIPE_STATUS_CANCELED = 'canceled';

    /**
     * @var string[] Params to expand when fetching subscriptions from the Stripe API
     */
    public static array $expandParams = [];

    public ?string $stripeId = null;

    public string $stripeStatus = self::STRIPE_STATUS_ACTIVE;

    public ?string $customerId = null;

    public ?string $latestInvoiceId = null;

    public ?DateTime $canceledAt = null;

    public ?DateTime $currentPeriodEnd = null;

    public ?DateTime $startDate = null;

    public ?DateTime $trialStart = null;

    public ?DateTime $trialEnd = null;

    /** @var string[]|null */
    private ?array $prices = null;

    /** @var Product[]|null */
    private ?array $products = null;

    private ?Customer $customer = null;

    #[Override]
    public static function displayName(): string
    {
        return t('Stripe Subscription', category: 'stripe');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('Stripe subscription', category: 'stripe');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Stripe Subscriptions', category: 'stripe');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('Stripe subscriptions', category: 'stripe');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'stripesubscription';
    }

    #[Override]
    public static function trackChanges(): bool
    {
        return true;
    }

    #[Override]
    public static function hasTitles(): bool
    {
        return true;
    }

    #[Override]
    public static function hasStatuses(): bool
    {
        return true;
    }

    #[Override]
    protected function showStatusField(): bool
    {
        return false;
    }

    #[Override]
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => t('Live'),
            self::STATUS_STRIPE_SCHEDULED => ['label' => t('Scheduled in Stripe', category: 'stripe'), 'color' => Color::Orange->value],
            self::STATUS_STRIPE_CANCELED => ['label' => t('Canceled in Stripe', category: 'stripe'), 'color' => Color::Red->value],
            self::STATUS_STRIPE_SUSPENDED => ['label' => t('Suspended in Stripe', category: 'stripe'), 'color' => Color::Blue->value],
            self::STATUS_DISABLED => t('Disabled'),
        ];
    }

    #[Override]
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status !== self::STATUS_ENABLED) {
            return $status;
        }

        return match ($this->stripeStatus) {
            self::STRIPE_STATUS_SCHEDULED => self::STATUS_STRIPE_SCHEDULED,
            self::STRIPE_STATUS_CANCELED => self::STATUS_STRIPE_CANCELED,
            self::STRIPE_STATUS_ACTIVE, self::STRIPE_STATUS_TRIALING => self::STATUS_LIVE,
            default => self::STATUS_STRIPE_SUSPENDED,
        };
    }

    #[Override]
    public static function find(): SubscriptionQuery
    {
        return new SubscriptionQuery;
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        return Fields::getLayoutByType(self::class);
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => t('All subscriptions', category: 'stripe'),
            ],
        ];
    }

    #[Override]
    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    #[Override]
    protected static function defineSortOptions(): array
    {
        return [
            'title' => self::displayName(),
            'stripeId' => [
                'label' => t('Stripe ID', category: 'stripe'),
                'orderBy' => 'stripe_subscriptions.stripeId',
                'defaultDir' => 'desc',
            ],
            'customerEmail' => [
                'label' => t('Customer Email', category: 'stripe'),
                'orderBy' => 'stripe_customerdata.email',
                'defaultDir' => 'asc',
            ],
            [
                'label' => t('Date Created'),
                'orderBy' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return [
            'stripeId' => ['label' => t('Stripe ID', category: 'stripe')],
            'stripeEdit' => ['label' => t('Stripe Edit', category: 'stripe')],
            'stripeStatus' => ['label' => t('Stripe Status', category: 'stripe')],
            'customerEmail' => ['label' => t('Customer Email', category: 'stripe')],
            'products' => ['label' => t('Products', category: 'stripe')],
            'id' => ['label' => t('ID')],
            'uid' => ['label' => t('UID')],
            'dateCreated' => ['label' => t('Date Created')],
            'dateUpdated' => ['label' => t('Date Updated')],
        ];
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['stripeId', 'stripeStatus', 'customerEmail', 'stripeEdit'];
    }

    #[Override]
    protected static function defineCardAttributes(): array
    {
        return [
            ...parent::defineCardAttributes(),
            'stripeId' => [
                'label' => t('Stripe ID', category: 'stripe'),
                'placeholder' => 'sub_xxxxxxxxxxx',
            ],
            'customerEmail' => [
                'label' => t('Customer Email', category: 'stripe'),
                'placeholder' => 'test@example.com',
            ],
        ];
    }

    #[Override]
    protected static function defineDefaultCardAttributes(): array
    {
        return ['customerEmail'];
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return ['email', 'stripeId'];
    }

    #[Override]
    public function getSearchKeywords(string $attribute): string
    {
        return match ($attribute) {
            'email' => $this->getCustomer()->email ?? '',
            default => parent::getSearchKeywords($attribute),
        };
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'stripeEdit' => StripeLink::html($this->getStripeEditUrl()),
            'stripeStatus' => $this->getStripeStatusHtml(),
            'customerEmail' => e($this->getCustomer()->email ?? ''),
            'products' => e(implode(', ', array_map(fn (Product $product) => $product->title, array_filter($this->getProducts() ?? [])))),
            default => (string) parent::attributeHtml($attribute),
        };
    }

    #[Override]
    protected function metadata(): array
    {
        $data = $this->getData();
        $formatter = I18N::getFormatter();
        $date = fn (?int $timestamp) => $timestamp ? $formatter->asDateTime($timestamp, 'short') : false;

        return [
            t('Stripe ID', category: 'stripe') => Html::tag('code', e((string) $this->stripeId)),
            t('Stripe Status', category: 'stripe') => $this->getStripeStatusHtml(),
            t('Customer', category: 'stripe') => $this->getCustomer() ? e((string) $this->getCustomer()->email) : false,
            t('Current period', category: 'stripe') => $this->getCurrentPeriodStart() && $this->getCurrentPeriodEnd()
                ? e($formatter->asDate($this->getCurrentPeriodStart(), 'short').' – '.$formatter->asDate($this->getCurrentPeriodEnd(), 'short'))
                : false,
            t('Cancel at period end', category: 'stripe') => ($data['cancel_at_period_end'] ?? false) ? t('Yes') : t('No'),
            t('Cancel at', category: 'stripe') => $date($data['cancel_at'] ?? null),
            t('Canceled at', category: 'stripe') => $date($data['canceled_at'] ?? null),
            t('Ended at', category: 'stripe') => $date($data['ended_at'] ?? null),
            t('Metadata', category: 'stripe') => $this->metadataHtml($data['metadata'] ?? []),
        ];
    }

    #[Override]
    public function canView(User $user): bool
    {
        return $user->can('accessPlugin-stripe');
    }

    #[Override]
    public function canSave(User $user): bool
    {
        return $user->can('accessPlugin-stripe');
    }

    #[Override]
    public function canDelete(User $user): bool
    {
        // Subscriptions are canceled in Stripe, not deleted in Craft; only unsaved drafts can go
        return $this->getIsDraft() && $user->can('accessPlugin-stripe');
    }

    #[Override]
    public function canCreateDrafts(User $user): bool
    {
        return $user->can('accessPlugin-stripe');
    }

    #[Override]
    protected function cpEditUrl(): ?string
    {
        return sprintf('stripe/subscriptions/%s', $this->getCanonicalId());
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return Url::cpUrl('stripe/subscriptions');
    }

    #[Override]
    protected function crumbs(): array
    {
        return [
            ['label' => self::pluralDisplayName(), 'url' => Url::cpUrl('stripe/subscriptions')],
        ];
    }

    /**
     * Resume and cancel actions, which change the subscription in Stripe.
     */
    #[Override]
    protected function extraActionMenuDescriptors(ElementActionContext $context = ElementActionContext::Editor): array
    {
        if ($this->stripeId === null || $this->stripeStatus === self::STRIPE_STATUS_CANCELED || $this->getIsRevision()) {
            return [];
        }

        $cancelsAtPeriodEnd = (bool) ($this->getData()['cancel_at_period_end'] ?? false);
        $action = fn (string $label, string $icon, string $path, array $params = [], ?string $confirm = null, bool $destructive = false) => array_filter([
            'label' => $label,
            'icon' => $icon,
            'destructive' => $destructive ?: null,
            'behavior' => array_filter([
                'type' => 'submit',
                'actionUrl' => Url::actionUrl($path),
                'params' => ['stripeId' => $this->stripeId, ...$params],
                'confirm' => $confirm,
            ]),
        ]);

        if ($cancelsAtPeriodEnd) {
            return [
                $action(t('Resume subscription', category: 'stripe'), 'play', 'stripe/subscriptions/resume'),
                $action(t('Cancel Immediately', category: 'stripe'), 'ban', 'stripe/subscriptions/cancel', ['immediately' => 1], t('Are you sure you want to cancel this subscription immediately?', category: 'stripe'), true),
            ];
        }

        return [
            $action(t('Cancel at period end', category: 'stripe'), 'ban', 'stripe/subscriptions/cancel', [], t('Are you sure you want to cancel this subscription at the end of its current period?', category: 'stripe'), true),
            $action(t('Cancel Immediately', category: 'stripe'), 'ban', 'stripe/subscriptions/cancel', ['immediately' => 1], t('Are you sure you want to cancel this subscription immediately?', category: 'stripe'), true),
        ];
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        $model = $isNew ? new SubscriptionModel(['id' => $this->id]) : SubscriptionModel::findOrFail($this->id);
        $model->stripeId = $this->stripeId;
        $model->dateCreated = $this->dateCreated;
        $model->dateUpdated = $this->dateUpdated;
        $model->save();

        parent::afterSave($isNew);
    }

    /**
     * Returns the start of the current billing period, as a Unix timestamp.
     *
     * Stripe API version `2025-03-31.basil` and later put the period on the subscription items. Data synced with
     * Stripe 1.x still has it on the subscription.
     */
    public function getCurrentPeriodStart(): ?int
    {
        return $this->currentPeriodValue('current_period_start');
    }

    /**
     * Returns the end of the current billing period, as a Unix timestamp.
     *
     * @see getCurrentPeriodStart()
     */
    public function getCurrentPeriodEnd(): ?int
    {
        return $this->currentPeriodValue('current_period_end');
    }

    private function currentPeriodValue(string $key): ?int
    {
        $data = $this->getData();
        $value = $data[$key] ?? $data['items']['data'][0][$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function getStripeEditUrl(): string
    {
        return app(Api::class)->dashboardUrl("subscriptions/$this->stripeId");
    }

    public function getStripeStatusHtml(): string
    {
        $color = match ($this->stripeStatus) {
            self::STRIPE_STATUS_ACTIVE => Color::Green,
            self::STRIPE_STATUS_SCHEDULED => Color::Orange,
            self::STRIPE_STATUS_CANCELED => Color::Red,
            self::STRIPE_STATUS_TRIALING => Color::Yellow,
            default => Color::Blue,
        };

        $label = t(ucwords(str_replace('_', ' ', $this->stripeStatus)), category: 'stripe');

        return Html::tag('span', '', ['class' => ['status', 'stripe-status', $color->value]]).e($label);
    }

    /**
     * @param  string|string[]|null  $value
     */
    public function setPrices(string|array|null $value): void
    {
        $this->prices = is_string($value) ? Json::decodeIfJson($value) : $value;
    }

    /**
     * Returns the Stripe IDs of the subscription’s prices.
     *
     * @return string[]
     */
    public function getPrices(): array
    {
        return Arr::wrap($this->prices);
    }

    /**
     * Returns the products the subscription’s items are for.
     *
     * @return Product[]|null
     */
    public function getProducts(): ?array
    {
        return $this->products ??= app(Products::class)->getProductsBySubscriptionId($this->stripeId);
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * Returns a billing portal URL where the customer can change the subscription.
     *
     * @param  array<string, mixed>  $params
     */
    public function getBillingPortalSessionUpdateUrl(?string $returnUrl = null, array $params = []): ?string
    {
        return $this->billingPortalFlowUrl('subscription_update', $returnUrl, $params);
    }

    /**
     * Returns a billing portal URL where the customer can cancel the subscription.
     *
     * @param  array<string, mixed>  $params
     */
    public function getBillingPortalSessionCancelUrl(?string $returnUrl = null, array $params = []): ?string
    {
        return $this->billingPortalFlowUrl('subscription_cancel', $returnUrl, $params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function billingPortalFlowUrl(string $flow, ?string $returnUrl, array $params): ?string
    {
        if ($this->getStatus() !== self::STATUS_LIVE || $this->customerId === null) {
            return '';
        }

        $params = Arr::merge([
            'flow_data' => [
                'type' => $flow,
                $flow => ['subscription' => $this->stripeId],
            ],
        ], $params);

        return app(BillingPortal::class)->getSessionUrl(
            $this->customerId,
            returnUrl: $returnUrl ? Url::siteUrl($returnUrl) : Url::siteUrl(),
            params: $params,
        );
    }
}
