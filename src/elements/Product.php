<?php

namespace craft\stripe\elements;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\ElementCollection;
use craft\elements\NestedElementManager;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\enums\PropagationMethod;
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\stripe\Plugin;
use craft\stripe\db\Table;
use craft\stripe\elements\conditions\products\ProductCondition;
use craft\stripe\elements\db\PriceQuery;
use craft\stripe\elements\db\ProductQuery;
use craft\stripe\helpers\Product as ProductHelper;
use craft\stripe\records\Product as ProductRecord;
use craft\stripe\web\assets\stripecp\StripeCpAsset;
use craft\web\CpScreenResponseBehavior;
use yii\helpers\Html as HtmlHelper;
use yii\web\Response;

/**
 * Product element type
 *
 * @property-read Price[]|null $prices the product’s prices
 */
class Product extends Element
{
    // Constants
    // -------------------------------------------------------------------------

    /**
     * Craft Statuses
     */
    public const STATUS_LIVE = 'live';
    public const STATUS_STRIPE_ARCHIVED = 'stripeArchived';

    /**
     * Stripe Statuses
     * @since 3.0
     */
    public const STRIPE_STATUS_ACTIVE = 'active';
    public const STRIPE_STATUS_ARCHIVED = 'archived';

    // Properties
    // -------------------------------------------------------------------------

    /**
     * @var string
     */
    public string $stripeStatus = 'active';

    /**
     * @var string|null
     */
    public ?string $stripeId = null;

    /**
     * @var array|null
     */
    private ?array $_data = null;

    /**
     * @see getPricesManager()
     */
    private NestedElementManager $_priceManager;

    /**
     * @var ElementCollection<Price> Prices
     * @see getPrices()
     */
    private ElementCollection $_prices;


    // Methods
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('stripe', 'Stripe Product');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('stripe', 'stripe product');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('stripe', 'Stripe Products');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('stripe', 'stripe products');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'stripeproduct';
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => Craft::t('app', 'Live'),
            self::STATUS_STRIPE_ARCHIVED => ['label' => Craft::t('stripe', 'Archived in Stripe'), 'color' => 'red'],
            self::STATUS_DISABLED => Craft::t('app', 'Disabled'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status === self::STATUS_ENABLED) {
            return match ($this->stripeStatus) {
                self::STRIPE_STATUS_ARCHIVED => self::STATUS_STRIPE_ARCHIVED,
                default => self::STATUS_LIVE,
            };
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(ProductQuery::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ProductCondition::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return Craft::$app->fields->getLayoutByType(Product::class);
    }

    /**
     * @inheritdoc
     */
    public function getSidebarHtml(bool $static): string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Craft::$app->getView()->registerAssetBundle(StripeCpAsset::class);
        $productCard = ProductHelper::renderCardHtml($this);
        return $productCard . parent::getSidebarHtml($static);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('stripe', 'All products'),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        $sortOptions = parent::defineSortOptions();

        $sortOptions['stripeId'] = [
            'label' => Craft::t('stripe', 'Stripe ID'),
            'orderBy' => 'stripe_productdata.stripeId',
            'defaultDir' => SORT_DESC,
        ];

        $sortOptions['stripeStatus'] = [
            'label' => Craft::t('stripe', 'Stripe Status'),
            'orderBy' => 'stripe_productdata.stripeStatus',
            'defaultDir' => SORT_DESC,
        ];

        return $sortOptions;
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'stripeId' => Craft::t('stripe', 'Stripe ID'),
            'stripeEdit' => Craft::t('stripe', 'Stripe Edit'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'stripeId',
            'stripeStatus',
        ];
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        // Get the source element IDs
        $sourceElementIds = array_map(fn(ElementInterface $element) => $element->id, $sourceElements);

        if ($handle == 'prices') {
            $map = (new Query())
                ->select([
                    'source' => 'ownerId',
                    'target' => 'id',
                ])
                ->from([Table::PRICES])
                ->where(['ownerId' => $sourceElementIds])
                ->all();

            return [
                'elementType' => Price::class,
                'map' => $map,
                'createElement' => function(PriceQuery $query, array $result, self $source) {
                    // set the addresses' owners to the source user elements
                    // (must get set before behaviors - see https://github.com/craftcms/cms/issues/13400)
                    return $query->createElement(['owner' => $source] + $result);
                },
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    public function getUriFormat(): ?string
    {
        return Plugin::getInstance()->getSettings()->productUriFormat;
    }

    /**
     * @inheritdoc
     */
    protected function previewTargets(): array
    {
        $previewTargets = [];
        $url = $this->getUrl();
        if ($url) {
            $previewTargets[] = [
                'label' => Craft::t('app', 'Primary {type} page', [
                    'type' => self::lowerDisplayName(),
                ]),
                'url' => $url,
            ];
        }
        return $previewTargets;
    }

    /**
     * @inheritdoc
     */
    protected function route(): array|string|null
    {
        if (!$this->previewing && $this->getStatus() != self::STATUS_LIVE) {
            return null;
        }

        $settings = Plugin::getInstance()->getSettings();

        if ($settings->productUriFormat) {
            return [
                'templates/render', [
                    'template' => $settings->productTemplate,
                    'variables' => [
                        'product' => $this,
                    ],
                ],
            ];
        }

        return null;
    }

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('viewProducts');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('saveProducts');
    }

//    public function canDuplicate(User $user): bool
//    {
//        if (parent::canDuplicate($user)) {
//            return true;
//        }
//        // todo: implement user permissions
//        return $user->can('saveProducts');
//    }

    public function canDelete(User $user): bool
    {
        // We normally cant delete stripe elements, but we can if we are in a draft state.
        if ($this->getIsDraft()) {
            return true;
        }

        return false;
//        if (parent::canSave($user)) {
//            return true;
//        }
//        // todo: implement user permissions
//        return $user->can('deleteProducts');
    }

    /**
     * @inheritdoc
     */
    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        return sprintf('stripe/products/%s', $this->getCanonicalId());
    }

    /**
     * @inheritdoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('stripe/products');
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'prices';
        return $names;
    }

    /**
     * @inheritdoc
     */
    protected function crumbs(): array
    {
        return [
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('stripe/products'),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = ProductRecord::findOne($this->id);

            if (!$record) {
                throw new \Exception('Invalid product ID: ' . $this->id);
            }
        } else {
            $record = new ProductRecord();
            $record->id = $this->id;
        }

        $record->stripeId = $this->stripeId;

        // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e re-saving
        $record->dateUpdated = $this->dateUpdated;
        $record->dateCreated = $this->dateCreated;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        $this->getPriceManager()->deleteNestedElements($this, $this->hardDelete);

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'stripeEdit':
                return HtmlHelper::a('', $this->getStripeEditUrl(), ['target' => '_blank', 'data' => ['icon' => 'external']]);
            case 'stripeStatus':
                return $this->getStripeStatusHtml();
            case 'stripeId':
                return $this->$attribute;
//            case 'options':
//                return collect($this->getOptions())->map(function($option) {
//                    return HtmlHelper::tag('span', $option['name'], [
//                        'title' => $option['name'] . ' option values: ' . collect($option['values'])->join(', '),
//                    ]);
//                })->join(',&nbsp;');
//            case 'tags':
//                return collect($this->getTags())->map(function($tag) {
//                    return HtmlHelper::tag('div', $tag, [
//                        'style' => 'margin-bottom: 2px;',
//                        'class' => 'token',
//                    ]);
//                })->join('&nbsp;');
//            case 'variants':
//                return collect($this->getVariants())->pluck('title')->map(fn($title) => StringHelper::toTitleCase($title))->join(',&nbsp;');
            default:
            {
                return parent::attributeHtml($attribute);
            }
        }
    }


    /**
     * Return URL to edit the product in Stripe Dashboard
     *
     * @return string
     */
    public function getStripeEditUrl(): string
    {
        $dashboardUrl = Plugin::getInstance()->dashboardUrl;
        $mode = Plugin::getInstance()->stripeMode;
        return "{$dashboardUrl}/{$mode}/products/{$this->stripeId}";
    }

    /**
     * @return string
     */
    public function getStripeStatusHtml(): string
    {
        $color = match ($this->stripeStatus) {
            'active' => 'green',
            'archived' => 'red',
            default => 'orange',
        };
        return "<span class='status $color'></span>" . StringHelper::titleize($this->stripeStatus);
    }

    /**
     * @param string|array $value
     * @return void
     */
    public function setData(string|array|null $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        $this->_data = $value;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->_data ?? [];
    }


    /**
     * Gets the product’s prices.
     *
     * @return ElementCollection<Price>
     */
    public function getPrices(): ElementCollection
    {
        if (!isset($this->_prices)) {
            if (!$this->id) {
                /** @var ElementCollection<Price> */
                return ElementCollection::make();
            }

            $this->_prices = $this->createPriceQuery()->collect();
        }

        return $this->_prices;
    }

    /**
     * Returns a nested element manager for the product’s prices.
     *
     * @return NestedElementManager
     */
    public function getPriceManager(): NestedElementManager
    {
        if (!isset($this->_priceManager)) {
            $this->_priceManager = new NestedElementManager(
                Price::class,
                fn() => $this->createPriceQuery(),
                [
                    'attribute' => 'prices',
                    'propagationMethod' => PropagationMethod::None,
                ],
            );
        }

        return $this->_priceManager;
    }

    private function createPriceQuery(): PriceQuery
    {
        return Price::find()
            ->owner($this)
            ->orderBy(['id' => SORT_ASC]);
    }
}