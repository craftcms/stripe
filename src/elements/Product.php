<?php

namespace craft\stripe\elements;

use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\stripe\records\Product as ProductRecord;
use craft\stripe\elements\conditions\ProductCondition;
use craft\stripe\elements\db\ProductQuery;
use craft\web\CpScreenResponseBehavior;
use yii\base\InvalidConfigException;
use yii\helpers\Html as HtmlHelper;
use yii\web\Response;

/**
 * Product element type
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
     * @var array
     */
    private array $_data = [];


    // Methods
    // -------------------------------------------------------------------------

    public static function displayName(): string
    {
        return Craft::t('stripe', 'Product');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('stripe', 'product');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('stripe', 'Products');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('stripe', 'products');
    }

    public static function refHandle(): ?string
    {
        return 'product';
    }

    public static function trackChanges(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return true;
    }

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
            self::STATUS_LIVE => Craft::t('stripe', 'Live'),
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

    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(ProductQuery::class, [static::class]);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ProductCondition::class, [static::class]);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('stripe', 'All products'),
            ],
        ];
    }

    protected static function defineActions(string $source): array
    {
        // List any bulk element actions here
        return [];
    }

    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'slug' => Craft::t('app', 'Slug'),
            'uri' => Craft::t('app', 'URI'),
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
            // ...
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'slug' => ['label' => Craft::t('app', 'Slug')],
            'uri' => ['label' => Craft::t('app', 'URI')],
            'link' => ['label' => Craft::t('app', 'Link'), 'icon' => 'world'],
            'id' => ['label' => Craft::t('app', 'ID')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
            // ...
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'link',
            'dateCreated',
            // ...
        ];
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function getUriFormat(): ?string
    {
        // If products should have URLs, define their URI format here
        return null;
    }

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

    protected function route(): array|string|null
    {
        // Define how products should be routed when their URLs are requested
        return [
            'templates/render',
            [
                'template' => 'site/template/path',
                'variables' => ['product' => $this],
            ]
        ];
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

    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('saveProducts');
    }

    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('deleteProducts');
    }

    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    protected function cpEditUrl(): ?string
    {
        return sprintf('products/%s', $this->getCanonicalId());
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('products');
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('products'),
            ],
        ]);
    }

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
     * @param string $attribute
     * @return string
     * @throws InvalidConfigException
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
//            case 'shopifyEdit':
//                return HtmlHelper::a('', $this->getShopifyEditUrl(), ['target' => '_blank', 'data' => ['icon' => 'external']]);
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
     * @param string|array $value
     * @return void
     */
    public function setData(string|array $value): void
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
}
