<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements;

use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Data\EagerLoadPlan;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Element\Enums\PropagationMethod;
use CraftCms\Cms\Element\NestedElementManager;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Database\Table;
use CraftCms\Stripe\Elements\Concerns\HasStripeData;
use CraftCms\Stripe\Elements\Conditions\ProductCondition;
use CraftCms\Stripe\Elements\Queries\PriceQuery;
use CraftCms\Stripe\Elements\Queries\ProductQuery;
use CraftCms\Stripe\Models\Product as ProductModel;
use CraftCms\Stripe\Plugin;
use CraftCms\Stripe\Support\StripeLink;
use Illuminate\Support\Facades\DB;
use Override;

use function CraftCms\Cms\t;

/**
 * A Stripe product, synced from Stripe.
 *
 * @since 2.0.0
 */
class Product extends Element
{
    use HasStripeData;

    public const string STATUS_LIVE = 'live';

    public const string STATUS_STRIPE_ARCHIVED = 'stripeArchived';

    public const string STRIPE_STATUS_ACTIVE = 'active';

    public const string STRIPE_STATUS_ARCHIVED = 'archived';

    /**
     * @var string[] Params to expand when fetching products from the Stripe API
     */
    public static array $expandParams = ['default_price'];

    public ?string $stripeId = null;

    public string $stripeStatus = self::STRIPE_STATUS_ACTIVE;

    /** @var array<string, ElementCollection<int, Price>> */
    private array $prices = [];

    private ?NestedElementManager $priceManager = null;

    #[Override]
    public static function displayName(): string
    {
        return t('Stripe Product', category: 'stripe');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('Stripe product', category: 'stripe');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Stripe Products', category: 'stripe');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('Stripe products', category: 'stripe');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'stripeproduct';
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
    public static function hasUris(): bool
    {
        return true;
    }

    #[Override]
    public static function isLocalized(): bool
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
            self::STATUS_DISABLED => t('Disabled'),
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
    public static function find(): ProductQuery
    {
        return new ProductQuery;
    }

    #[Override]
    public static function createCondition(): ElementConditionInterface
    {
        return new ProductCondition(self::class);
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
                'label' => t('All products', category: 'stripe'),
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
                'orderBy' => 'stripe_productdata.stripeId',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Date Created'),
                'orderBy' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Date Updated'),
                'orderBy' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return [
            'stripeId' => ['label' => t('Stripe ID', category: 'stripe')],
            'stripeStatus' => ['label' => t('Stripe Status', category: 'stripe')],
            'stripeEdit' => ['label' => t('Stripe Edit', category: 'stripe')],
            'slug' => ['label' => t('Slug')],
            'uri' => ['label' => t('URI')],
            'link' => ['label' => t('Link'), 'icon' => 'world'],
            'id' => ['label' => t('ID')],
            'uid' => ['label' => t('UID')],
            'dateCreated' => ['label' => t('Date Created')],
            'dateUpdated' => ['label' => t('Date Updated')],
        ];
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['stripeId', 'stripeStatus', 'stripeEdit', 'link'];
    }

    #[Override]
    protected static function defineCardAttributes(): array
    {
        return [
            ...parent::defineCardAttributes(),
            'stripeId' => [
                'label' => t('Stripe ID', category: 'stripe'),
                'placeholder' => 'prod_xxxxxxxxxxx',
            ],
        ];
    }

    #[Override]
    protected static function defineDefaultCardAttributes(): array
    {
        return ['stripeId'];
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return ['stripeId'];
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'stripeEdit' => $this->stripeEditLinkHtml(),
            'stripeStatus' => $this->getStripeStatusHtml(),
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
            t('Default price', category: 'stripe') => fn () => $this->getDefaultPrice()->title ?? false,
            t('Description', category: 'stripe') => isset($data['description']) ? e($data['description']) : false,
            t('Metadata', category: 'stripe') => $this->metadataHtml($data['metadata'] ?? []),
            t('Open in Stripe', category: 'stripe') => $this->stripeEditLinkHtml(t('Open in Stripe', category: 'stripe')),
        ];
    }

    #[Override]
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle === 'prices') {
            $map = DB::table(Table::PRICES)
                ->select(['primaryOwnerId as source', 'id as target'])
                ->whereIn('primaryOwnerId', array_map(fn (ElementInterface $element) => $element->id, $sourceElements))
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            return [
                'elementType' => Price::class,
                'map' => $map,
                'createElement' => fn (PriceQuery $query, array $result, self $source) => $query->createElement(['owner' => $source] + $result),
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    #[Override]
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($plan->handle === 'prices') {
            $this->prices[md5(serialize($plan->criteria))] = ElementCollection::make($elements);
        }

        parent::setEagerLoadedElements($handle, $elements, $plan);
    }

    #[Override]
    public function getUriFormat(): ?string
    {
        return Plugin::getInstance()->getSettings()->productUriFormat ?: null;
    }

    #[Override]
    protected function previewTargets(): array
    {
        $url = $this->getUrl();

        return $url ? [[
            'label' => t('Primary {type} page', ['type' => self::lowerDisplayName()]),
            'url' => $url,
        ]] : [];
    }

    #[Override]
    protected function route(): ?array
    {
        if (! $this->previewing && $this->getStatus() !== self::STATUS_LIVE) {
            return null;
        }

        $settings = Plugin::getInstance()->getSettings();

        if (! $settings->productUriFormat || ! $settings->productTemplate) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $settings->productTemplate,
                'variables' => ['product' => $this],
            ],
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
        // Products are deleted in Stripe, not in Craft; only unsaved drafts can go
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
        return sprintf('stripe/products/%s', $this->getCanonicalId());
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return Url::cpUrl('stripe/products');
    }

    #[Override]
    protected function crumbs(): array
    {
        return [
            ['label' => self::pluralDisplayName(), 'url' => Url::cpUrl('stripe/products')],
        ];
    }

    #[Override]
    public function extraFields(): array
    {
        return [...parent::extraFields(), 'prices'];
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        $model = $isNew ? new ProductModel(['id' => $this->id]) : ProductModel::findOrFail($this->id);
        $model->stripeId = $this->stripeId;
        $model->dateCreated = $this->dateCreated;
        $model->dateUpdated = $this->dateUpdated;
        $model->save();

        parent::afterSave($isNew);

        if ($isNew) {
            // The webhook for a new price can arrive before the one for its product
            foreach (Price::find()->stripeProductId($this->stripeId)->status(null)->all() as $price) {
                /** @var Price $price */
                if ($price->getPrimaryOwnerId() === null) {
                    $price->setPrimaryOwnerId($this->id);
                    $price->setOwnerId($this->id);
                    Elements::saveElement($price);
                }
            }
        }
    }

    #[Override]
    public function beforeDelete(): bool
    {
        if (! parent::beforeDelete()) {
            return false;
        }

        $this->getPriceManager()->deleteNestedElements($this, $this->hardDelete);

        return true;
    }

    public function getStripeEditUrl(): string
    {
        return app(Api::class)->dashboardUrl("products/$this->stripeId");
    }

    public function getStripeStatusHtml(): string
    {
        $color = match ($this->stripeStatus) {
            self::STRIPE_STATUS_ACTIVE => 'green',
            self::STRIPE_STATUS_ARCHIVED => 'red',
            default => 'orange',
        };

        return Html::tag('span', '', ['class' => ['status', $color]]).e(ucfirst($this->stripeStatus));
    }

    /**
     * Returns the product’s default price, if it has one.
     */
    public function getDefaultPrice(): ?Price
    {
        $defaultPriceId = $this->getData()['default_price'] ?? null;

        if (is_array($defaultPriceId)) {
            $defaultPriceId = $defaultPriceId['id'] ?? null;
        }

        if ($defaultPriceId === null) {
            return null;
        }

        return $this->getPrices()->first(fn (Price $price) => $price->stripeId === $defaultPriceId);
    }

    /**
     * Returns the product’s prices.
     *
     * @param  array<string, mixed>  $criteria  Extra price query criteria
     * @return ElementCollection<int, Price>
     */
    public function getPrices(array $criteria = []): ElementCollection
    {
        if (! $this->id) {
            return ElementCollection::make();
        }

        $key = md5(serialize($criteria));

        if (! isset($this->prices[$key])) {
            $query = $this->createPriceQuery();

            if ($criteria !== []) {
                PriceQuery::configure($query, $criteria);
            }

            $this->prices[$key] = $query->get();
        }

        return $this->prices[$key];
    }

    public function getPriceManager(): NestedElementManager
    {
        return $this->priceManager ??= new NestedElementManager(
            Price::class,
            fn (self $product) => $product->createPriceQuery(),
            [
                'attribute' => 'prices',
                'propagationMethod' => PropagationMethod::None,
            ],
        );
    }

    private function createPriceQuery(): PriceQuery
    {
        return Price::find()
            ->owner($this)
            ->status(null)
            ->orderBy('elements.id');
    }

    private function stripeEditLinkHtml(string $label = ''): string
    {
        return StripeLink::html($this->getStripeEditUrl(), $label);
    }
}
