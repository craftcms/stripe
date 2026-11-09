<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Concerns\NestedElement;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Contracts\NestedElementInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Checkout;
use CraftCms\Stripe\Database\Table;
use CraftCms\Stripe\Elements\Concerns\HasStripeData;
use CraftCms\Stripe\Elements\Conditions\PriceCondition;
use CraftCms\Stripe\Elements\Queries\PriceQuery;
use CraftCms\Stripe\Models\Price as PriceModel;
use CraftCms\Stripe\Support\PriceFormatter;
use CraftCms\Stripe\Support\StripeLink;
use Illuminate\Support\Facades\DB;
use Override;

use function CraftCms\Cms\t;

/**
 * A Stripe price, synced from Stripe and owned by its product.
 *
 * @since 3.0.0
 */
class Price extends Element implements NestedElementInterface
{
    use HasStripeData;
    use NestedElement;

    public const string STATUS_LIVE = 'live';

    public const string STATUS_STRIPE_ARCHIVED = 'stripeArchived';

    public const string STRIPE_STATUS_ACTIVE = 'active';

    public const string STRIPE_STATUS_ARCHIVED = 'archived';

    /**
     * @var string[] Params to expand when fetching prices from the Stripe API
     */
    public static array $expandParams = ['currency_options', 'tiers'];

    public ?string $stripeId = null;

    public string $stripeStatus = self::STRIPE_STATUS_ACTIVE;

    public ?float $unitAmount = null;

    public ?string $type = null;

    public ?string $stripeProductId = null;

    public ?string $primaryCurrency = null;

    /** @var string[]|null */
    private ?array $currencies = null;

    #[Override]
    public static function displayName(): string
    {
        return t('Stripe Price', category: 'stripe');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('Stripe price', category: 'stripe');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Stripe Prices', category: 'stripe');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('Stripe prices', category: 'stripe');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'stripeprice';
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
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => t('Live'),
            self::STATUS_STRIPE_ARCHIVED => ['label' => t('Archived in Stripe', category: 'stripe'), 'color' => Color::Red->value],
        ];
    }

    #[Override]
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status === self::STATUS_ENABLED) {
            return $this->stripeStatus === self::STRIPE_STATUS_ARCHIVED ? self::STATUS_STRIPE_ARCHIVED : self::STATUS_LIVE;
        }

        return $status;
    }

    #[Override]
    public static function find(): PriceQuery
    {
        return new PriceQuery;
    }

    #[Override]
    public static function createCondition(): ElementConditionInterface
    {
        return new PriceCondition(self::class);
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        return Fields::getLayoutByType(self::class);
    }

    protected function ownerType(): ?string
    {
        return Product::class;
    }

    /**
     * @param  string|string[]|null  $value
     */
    public function setCurrencies(string|array|null $value): void
    {
        $this->currencies = is_string($value) ? Json::decodeIfJson($value) : $value;
    }

    /**
     * @return string[]
     */
    public function getCurrencies(): array
    {
        return is_array($this->currencies) ? $this->currencies : [];
    }

    /**
     * The unit amount, formatted in the price’s currency.
     */
    public function unitAmount(): ?string
    {
        return PriceFormatter::asUnitAmount($this->getData());
    }

    /**
     * The price per unit and billing interval, such as “$10.00/month”.
     */
    public function unitPrice(): string
    {
        return PriceFormatter::asUnitPrice($this->getData());
    }

    public function pricePerUnit(): string
    {
        return PriceFormatter::asPricePerUnit($this->getData());
    }

    public function interval(): string
    {
        return PriceFormatter::getInterval($this->getData());
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
                'orderBy' => 'stripe_pricedata.stripeId',
                'defaultDir' => 'desc',
            ],
        ];
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return [
            'primaryCurrency' => ['label' => t('Primary Currency', category: 'stripe')],
            'stripeId' => ['label' => t('Stripe ID', category: 'stripe')],
            'stripeStatus' => ['label' => t('Stripe Status', category: 'stripe')],
            'stripeEdit' => ['label' => t('Stripe Edit', category: 'stripe')],
            'type' => ['label' => t('Type', category: 'stripe')],
            'unitPrice' => ['label' => t('Unit Price', category: 'stripe')],
            'pricePerUnit' => ['label' => t('Price per Unit', category: 'stripe')],
            'interval' => ['label' => t('Interval', category: 'stripe')],
            'id' => ['label' => t('ID')],
            'dateCreated' => ['label' => t('Date Created')],
            'dateUpdated' => ['label' => t('Date Updated')],
        ];
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['stripeId', 'stripeStatus', 'type', 'unitPrice'];
    }

    #[Override]
    protected static function defineCardAttributes(): array
    {
        return [
            ...parent::defineCardAttributes(),
            'stripeId' => [
                'label' => t('Stripe ID', category: 'stripe'),
                'placeholder' => 'price_xxxxxxxxxxx',
            ],
            'unitPrice' => [
                'label' => t('Unit Price', category: 'stripe'),
                'placeholder' => '$12.34/month',
            ],
            'type' => [
                'label' => t('Type', category: 'stripe'),
                'placeholder' => 'recurring',
            ],
        ];
    }

    #[Override]
    protected static function defineDefaultCardAttributes(): array
    {
        return ['stripeId', 'unitPrice'];
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'stripeEdit' => StripeLink::html($this->getStripeEditUrl()),
            'stripeStatus' => $this->getStripeStatusHtml(),
            'type' => e($this->type ?? ''),
            'pricePerUnit' => e($this->pricePerUnit()),
            'unitPrice' => e($this->unitPrice()),
            'primaryCurrency' => e(strtoupper((string) $this->primaryCurrency)),
            'interval' => e($this->interval()),
            default => (string) parent::attributeHtml($attribute),
        };
    }

    #[Override]
    protected function metadata(): array
    {
        $data = $this->getData();

        return [
            t('Stripe ID', category: 'stripe') => Html::tag('code', e((string) $this->stripeId)),
            t('Stripe Status', category: 'stripe') => $this->getStripeStatusHtml(),
            t('Product', category: 'stripe') => fn () => $this->getProduct()?->title !== null ? e($this->getProduct()->title) : false,
            t('Unit Price', category: 'stripe') => $data !== [] ? e($this->unitPrice()) : false,
            t('Interval', category: 'stripe') => $data !== [] ? e($this->interval()) : false,
            t('Currencies', category: 'stripe') => $this->getCurrencies() !== [] ? e(strtoupper(implode(', ', $this->getCurrencies()))) : false,
            t('Metadata', category: 'stripe') => $this->metadataHtml($data['metadata'] ?? []),
        ];
    }

    #[Override]
    public function canView(User $user): bool
    {
        return true;
    }

    #[Override]
    public function canSave(User $user): bool
    {
        return true;
    }

    #[Override]
    public function canDelete(User $user): bool
    {
        // Prices are deleted in Stripe, not Craft; only unsaved drafts can go
        return $this->getIsDraft();
    }

    #[Override]
    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        $model = $isNew ? new PriceModel(['id' => $this->id]) : PriceModel::findOrFail($this->id);
        $model->stripeId = $this->stripeId;
        $model->primaryOwnerId = $this->getPrimaryOwnerId();
        $model->dateCreated = $this->dateCreated;
        $model->dateUpdated = $this->dateUpdated;
        $dirtyAttributes = array_keys($model->getDirty());
        $model->save();

        $this->saveProductOwnership($isNew);
        $this->setDirtyAttributes($dirtyAttributes);

        parent::afterSave($isNew);
    }

    public function getStripeEditUrl(): string
    {
        return app(Api::class)->dashboardUrl("prices/$this->stripeId");
    }

    public function getStripeStatusHtml(): string
    {
        $color = $this->stripeStatus === self::STRIPE_STATUS_ARCHIVED ? 'red' : 'green';

        return Html::tag('span', '', ['class' => ['status', $color]]).e(ucfirst($this->stripeStatus));
    }

    /**
     * Returns the product the price belongs to.
     */
    public function getProduct(): ?Product
    {
        if (! $this->getPrimaryOwnerId()) {
            return null;
        }

        $owner = $this->getOwner();

        return $owner instanceof Product ? $owner : null;
    }

    /**
     * Returns a Stripe Checkout URL for one of this price.
     *
     * @param  string|User|false|null  $customer  An email, a user, `false` for a guest checkout, or `null` for the current user
     * @param  array<string, mixed>|null  $params  Extra Checkout session params
     */
    public function getCheckoutUrl(
        string|User|false|null $customer = null,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        ?array $params = null,
    ): string {
        return app(Checkout::class)->getCheckoutUrl(
            [['price' => $this->stripeId, 'quantity' => 1]],
            $customer,
            $successUrl,
            $cancelUrl,
            $params,
        );
    }

    /**
     * Records the price in its product’s `elements_owners` rows. Prices don’t belong to a field, so
     * the nested element trait’s own ownership saving doesn’t apply.
     */
    private function saveProductOwnership(bool $isNew): void
    {
        $ownerId = $this->getOwnerId();

        if (! $ownerId || ! $this->saveOwnership) {
            return;
        }

        if (! isset($this->sortOrder) && (! $isNew || $this->duplicateOf)) {
            $elementId = $this->duplicateOf ? ($this->draftId ? $this->duplicateOf->id : null) : $this->id;

            if ($elementId) {
                $this->sortOrder = DB::table(CraftTable::ELEMENTS_OWNERS)
                    ->where('elementId', $elementId)
                    ->where('ownerId', $ownerId)
                    ->value('sortOrder') ?: null;
            }
        }

        if (! isset($this->sortOrder)) {
            $max = DB::table(CraftTable::ELEMENTS_OWNERS.' as eo')
                ->join(Table::PRICES.' as p', 'p.id', '=', 'eo.elementId')
                ->where('eo.ownerId', $ownerId)
                ->max('eo.sortOrder');
            $this->sortOrder = $max ? $max + 1 : 1;
        }

        DB::table(CraftTable::ELEMENTS_OWNERS)->upsert(
            [['elementId' => $this->id, 'ownerId' => $ownerId, 'sortOrder' => $this->sortOrder]],
            ['elementId', 'ownerId'],
            ['sortOrder'],
        );
    }
}
