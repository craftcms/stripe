<?php

namespace craft\stripe\elements;

use Craft;
use craft\base\Element;
use craft\base\NestedElementInterface;
use craft\base\NestedElementTrait;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\User;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\stripe\db\Table;
use craft\stripe\Plugin;
use craft\stripe\elements\db\PriceQuery;
use craft\stripe\helpers\Price as PriceHelper;
use craft\stripe\records\Price as PriceRecord;
use craft\stripe\web\assets\stripecp\StripeCpAsset;

/**
 * Price element type
 *
 * @property-read Product|null $product the product this price belongs to
 */
class Price extends Element implements NestedElementInterface
{
    use NestedElementTrait;

    // Constants
    // -------------------------------------------------------------------------

    /**
     * Craft Statuses
     */
    public const STATUS_LIVE = 'live';
    public const STATUS_STRIPE_ARCHIVED = 'stripeArchived';

    /**
     * Stripe Statuses
     */
    public const STRIPE_STATUS_ACTIVE = 'active';
    public const STRIPE_STATUS_ARCHIVED = 'archived';

    // Properties
    // -------------------------------------------------------------------------

    /**
     * @var string|null
     */
    public ?string $stripeId = null;

    /**
     * @var string
     */
    public string $stripeStatus = 'active';

    /**
     * @var array|null
     */
    private ?array $_data = null;

    /**
     * @var Product|null Product
     * @see getProduct()
     */
    private ?Product $_product = null;


    // Methods
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('stripe', 'Stripe Price');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('stripe', 'stripe price');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('stripe', 'Stripe Prices');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('stripe', 'stripe prices');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'stripeprice';
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
        return false;
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
            //self::STATUS_DISABLED => Craft::t('app', 'Disabled'),
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
        return Craft::createObject(PriceQuery::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return Craft::$app->fields->getLayoutByType(Price::class);
    }

    /**
     * @inheritdoc
     */
    public function getSidebarHtml(bool $static): string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Craft::$app->getView()->registerAssetBundle(StripeCpAsset::class);
        $priceCard = PriceHelper::renderCardHtml($this);
        return $priceCard . parent::getSidebarHtml($static);
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
            'orderBy' => 'stripe_pricedata.stripeId',
            'defaultDir' => SORT_DESC,
        ];

        $sortOptions['stripeStatus'] = [
            'label' => Craft::t('stripe', 'Stripe Status'),
            'orderBy' => 'stripe_pricedata.stripeStatus',
            'defaultDir' => SORT_DESC,
        ];

        return $sortOptions;
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'stripeId' => Craft::t('stripe', 'Stripe ID'),
            'stripeEdit' => Craft::t('stripe', 'Stripe Edit'),
            'id' => ['label' => Craft::t('app', 'ID')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
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

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('viewPrices');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('savePrices');
    }

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
//        return $user->can('deletePrices');
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
        return null;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = PriceRecord::findOne($this->id);

            if (!$record) {
                throw new \Exception('Invalid price ID: ' . $this->id);
            }
        } else {
            $record = new PriceRecord();
            $record->id = $this->id;
        }

        $record->stripeId = $this->stripeId;
        $record->primaryOwnerId = $this->getPrimaryOwnerId();

        // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e re-saving
        $record->dateUpdated = $this->dateUpdated;
        $record->dateCreated = $this->dateCreated;

        // Capture the dirty attributes from the record
        $dirtyAttributes = array_keys($record->getDirtyAttributes());

        $record->save(false);

        $ownerId = $this->getOwnerId();
        if ($ownerId && $this->saveOwnership) {
            if (!isset($this->sortOrder) && (!$isNew || $this->duplicateOf)) {
                // figure out if we should proceed this way
                // if we're dealing with an element that's being duplicated, and it has a draftId
                // it means we're creating a draft of something
                // if we're duplicating element via duplicate action - draftId would be empty
                $elementId = null;
                if ($this->duplicateOf) {
                    if ($this->draftId) {
                        $elementId = $this->duplicateOf->id;
                    }
                } else {
                    // if we're not duplicating - use element's id
                    $elementId = $this->id;
                }
                if ($elementId) {
                    $this->sortOrder = (new Query())
                        ->select('sortOrder')
                        ->from(CraftTable::ELEMENTS_OWNERS)
                        ->where([
                            'elementId' => $elementId,
                            'ownerId' => $ownerId,
                        ])
                        ->scalar() ?: null;
                }
            }
            if (!isset($this->sortOrder)) {
                $max = (new Query())
                    ->from(['eo' => CraftTable::ELEMENTS_OWNERS])
                    ->innerJoin(['a' => Table::PRICES], '[[a.id]] = [[eo.elementId]]')
                    ->where([
                        'eo.ownerId' => $ownerId,
                    ])
                    ->max('[[eo.sortOrder]]');
                $this->sortOrder = $max ? $max + 1 : 1;
            }
            if ($isNew) {
                Db::insert(CraftTable::ELEMENTS_OWNERS, [
                    'elementId' => $this->id,
                    'ownerId' => $ownerId,
                    'sortOrder' => $this->sortOrder,
                ]);
            } else {
                Db::update(CraftTable::ELEMENTS_OWNERS, [
                    'sortOrder' => $this->sortOrder,
                ], [
                    'elementId' => $this->id,
                    'ownerId' => $ownerId,
                ]);
            }
        }

        $this->setDirtyAttributes($dirtyAttributes);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'stripeEdit':
                return Html::a('', $this->getStripeEditUrl(), ['target' => '_blank', 'data' => ['icon' => 'external']]);
            case 'stripeStatus':
                return $this->getStripeStatusHtml();
            case 'stripeId':
                return $this->$attribute;
            default:
            {
                return parent::attributeHtml($attribute);
            }
        }
    }

    /**
     * Return URL to edit the price in Stripe Dashboard
     *
     * @return string
     */
    public function getStripeEditUrl(): string
    {
        return Plugin::getInstance()->stripeBaseUrl . "/prices/{$this->stripeId}";
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
     * Gets the product this price belongs to
     *
     * @return Product|null
     */
    public function getProduct(): Product|null
    {
        if (!isset($this->_product)) {
            if (!$this->getPrimaryOwnerId()) {
                return null;
            }

            $this->_product = $this->getOwner();
        }

        return $this->_product;
    }
}
