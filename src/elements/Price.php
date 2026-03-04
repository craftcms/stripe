<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements;

use Craft;
use craft\base\Element;
use craft\base\NestedElementInterface;
use craft\base\NestedElementTrait;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\User;
use craft\enums\Color;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\MoneyHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\UserGroup;
use craft\stripe\db\Table;
use craft\stripe\elements\conditions\prices\PriceCondition;
use craft\stripe\elements\db\PriceQuery;
use craft\stripe\helpers\Price as PriceHelper;
use craft\stripe\Plugin;
use craft\stripe\records\Price as PriceRecord;
use craft\stripe\web\assets\stripecp\StripeCpAsset;
use Money\Currency;
use Money\Money;
use Stripe\Price as StripePrice;
use Throwable;
use yii\base\InvalidConfigException;

/**
 * Price element type
 *
 * @property-read Product|null $product the product this price belongs to
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @method Product|null getOwner()
 * @method Product|null getPrimaryOwner()
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
    public ?array $currencies = null;

    /**
     * @var float|null
     */
    public ?float $unitAmount = null;

    /**
     * @var string|null
     */
    public ?string $type = null;

    /**
     * @var string|null
     */
    public ?string $stripeProductId = null;

    /**
     * @var string|null
     */
    public ?string $primaryCurrency = null;

    /**
     * @var array|null
     */
    private ?array $_data = null;

    /**
     * @var Product|null Product
     * @see getProduct()
     */
    private ?Product $_product = null;

    /**
     * @var UserGroup[]|null
     */
    private ?array $_userGroupAssignments = null;

    /**
     * @var int
     */
    private ?array $_userGroupAssignmentIds = null;

    /**
     * @var array|string[] Array of params that should be expanded when fetching Price from the Stripe API
     */
    public static array $expandParams = [
        'currency_options',
        'tiers',
    ];


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
        return Craft::t('stripe', 'Stripe Price');
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
        return Craft::t('stripe', 'Stripe Prices');
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
            self::STATUS_STRIPE_ARCHIVED => ['label' => Craft::t('stripe', 'Archived in Stripe'), 'color' => Color::Red],
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
    public static function find(): PriceQuery
    {
        return Craft::createObject(PriceQuery::class, [static::class]);
    }

    /**
     * @inerhitdoc
     */
    public static function createCondition(): PriceCondition
    {
        return Craft::createObject(PriceCondition::class, [static::class]);
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
        return parent::getSidebarHtml($static) . $priceCard;
    }

    /**
     * Returns whether the price is configured as recurring.
     */
    public function isRecurring(): bool
    {
        return $this->type === StripePrice::TYPE_RECURRING;
    }

    /**
     * Returns price amount as number & currency.
     *
     * examples:
     * £10.50
     * $13.35
     * ¥1,000
     * £6.00 (when the price is: £6.00 per group of 10)
     * £10.00 (when the price is: Starts at £10.00 per unit + £0.00)
     * Customer chooses
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function unitAmount(): string
    {
        return PriceHelper::asUnitAmount($this->_data);
    }

    /**
     * Returns the unit price of the Stripe Price object.
     * It can be loosely thought of as price per unit + interval.
     *
     * examples:
     * £10.00
     * £10.00/month
     * £30 every 3 month
     * £5.00 per group of 10 every 3 month
     * Customer input price
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function unitPrice(): string
    {
        return PriceHelper::asUnitPrice($this->_data);
    }

    /**
     * Returns the price per unit
     *
     * examples:
     * £10.00
     * £5.00 per group of 10
     * Customer chooses
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function pricePerUnit(): string
    {
        return PriceHelper::asPricePerUnit($this->_data);
    }

    /**
     * Returns the interval of the price.
     *
     * examples:
     * One-time
     * Every 1 month
     *
     * @return string
     */
    public function interval(): string
    {
        return PriceHelper::getInterval($this->_data);
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

        unset($sortOptions['stripeEdit']);
        unset($sortOptions['unitPrice']);
        unset($sortOptions['pricePerUnit']);
        unset($sortOptions['interval']);
        unset($sortOptions['currency']);
        unset($sortOptions['type']);

        $sortOptions['title'] = self::displayName();

        $sortOptions['stripeId'] = [
            'label' => Craft::t('stripe', 'Stripe ID'),
            'orderBy' => 'stripeId',
            'defaultDir' => SORT_DESC,
        ];

        return $sortOptions;
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'primaryCurrency' => Craft::t('stripe', 'Primary Currency'),
            'stripeId' => Craft::t('stripe', 'Stripe ID'),
            'stripeEdit' => Craft::t('stripe', 'Stripe Edit'),
            'type' => Craft::t('stripe', 'Type'),
            'unitPrice' => Craft::t('stripe', 'Unit Price'),
            'pricePerUnit' => Craft::t('stripe', 'Price per Unit'),
            'interval' => Craft::t('stripe', 'Interval'),
            'currency' => Craft::t('stripe', 'Currency'),
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
            'type',
            'unitPrice',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineCardAttributes(): array
    {
        return array_merge(parent::defineCardAttributes(), [
            'primaryCurrency' => [
                'label' => Craft::t('stripe', 'Primary Currency'),
                'placeholder' => 'USD',
            ],
            'stripeId' => [
                'label' => Craft::t('stripe', 'Stripe ID'),
                'placeholder' => 'price_xxxxxxxxxxx',
            ],
            'stripeEdit' => [
                'label' => Craft::t('stripe', 'Stripe Edit'),
                'placeholder' => Html::a('', "#", ['target' => '_blank', 'data' => ['icon' => 'external']]),
            ],
            'type' => [
                'label' => Craft::t('stripe', 'Type'),
                'placeholder' => StripePrice::TYPE_RECURRING,
            ],
            'unitPrice' => [
                'label' => Craft::t('stripe', 'Unit Price'),
                'placeholder' => MoneyHelper::toString(new Money(1234, new Currency('USD'))) . '/month',
            ],
            'pricePerUnit' => [
                'label' => Craft::t('stripe', 'Price per Unit'),
                'placeholder' => MoneyHelper::toString(new Money(1234, new Currency('USD'))),
            ],
            'interval' => [
                'label' => Craft::t('stripe', 'Interval'),
                'placeholder' => PriceHelper::getInterval([
                    'recurring' => [
                        'interval_count' => 1,
                        'interval' => 'month',
                    ],
                ]),
            ],
            'currency' => [
                'label' => Craft::t('stripe', 'Currency'),
                'placeholder' => 'USD',
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function attributePreviewHtml(array $attribute): mixed
    {
        return match ($attribute['value']) {
            'stripeEdit', 'link' => $attribute['placeholder'],
            default => ElementHelper::attributeHtml($attribute['placeholder'] ?? $attribute['label']),
        };
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultCardAttributes(): array
    {
        return [
            'stripeId',
        ];
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['userGroupAssignmentIds'], 'safe'];
        $rules[] = [
            ['userGroupAssignmentIds'],
            function($attribute, $params, $validator, $current) {
                $allGroupIds = ArrayHelper::getColumn(Craft::$app->getUserGroups()->getAllGroups(), 'id');
                $selectedGroupIds = ArrayHelper::getColumn($this->getUserGroupAssignments(), 'id');

                if (count(array_diff($selectedGroupIds, $allGroupIds)) > 0) {
                    $this->addError($attribute, Craft::t('stripe', 'One or more of the selected user groups are invalid.'));
                }
            },
        ];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        // We normally cant delete stripe elements, but we can if we are in a draft state.
        if ($this->getIsDraft()) {
            return true;
        }

        return false;
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
        return sprintf('stripe/products/%s/prices/%s', $this->getOwnerId(), $this->getCanonicalId());
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'userGroupAssignments';
        $names[] = 'userGroupAssignmentIds';
        return $names;
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

            Db::upsert(CraftTable::ELEMENTS_OWNERS, [
                'elementId' => $this->id,
                'ownerId' => $ownerId,
                'sortOrder' => $this->sortOrder,
            ], [
                'elementId' => $this->id,
                'ownerId' => $ownerId,
            ]);
        }

        $this->setDirtyAttributes($dirtyAttributes);

        // Update join records with new groups:
        $this->saveUserGroupAssignments();

        parent::afterSave($isNew);
    }

    /**
     * Updates user group assignment records after a save.
     *
     * This method lifts most of its logic from {@see craft\services\Users::assignUserToGroups()}.
     */
    protected function saveUserGroupAssignments(): void
    {
        $db = Craft::$app->getDb();

        $oldGroupIds = (new Query())
            ->select(['groupId'])
            ->from([Table::PRICES_USERGROUPS])
            ->where(['priceId' => $this->id])
            ->column($db);

        // Build an array of new user groups, so we can unset them easily:
        $newGroupIds = array_flip(array_unique(ArrayHelper::getColumn($this->getUserGroupAssignments(), 'id')));

        $removedGroupIds = [];

        foreach ($oldGroupIds as $oldGroupId) {
            // Is this group still present in the new data?
            if (isset($newGroupIds[$oldGroupId])) {
                // Ok, let’s avoid deleting + re-creating the record unnecessarily:
                unset($newGroupIds[$oldGroupId]);
            } else {
                $removedGroupIds[] = $oldGroupId;
            }
        }

        if (empty($removedGroupIds) && empty($newGroupIds)) {
            // There was no change to the selected groups; we don’t have to touch the database!
            return;
        }

        // Whichever keys are left here (after culling with `unset()`) are our “real” new values:
        $newGroupIds = array_keys($newGroupIds);

        // Wrap all our group updates in a transaction:
        $transaction = $db->beginTransaction();

        try {
            if (!empty($newGroupIds)) {
                $values = [];
                foreach ($newGroupIds as $groupId) {
                    $values[] = [$this->id, $groupId];
                }
                Db::batchInsert(Table::PRICES_USERGROUPS, ['priceId', 'groupId'], $values, $db);
            }

            if (!empty($removedGroupIds)) {
                Db::delete(Table::PRICES_USERGROUPS, [
                    'priceId' => $this->id,
                    'groupId' => $removedGroupIds,
                ], [], $db);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // The Users service invalidates element index caches, at this point.
        // We have the luxury of being in a private method, which will always be invoked within a typical element save!
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'stripeEdit' => Html::a('', $this->getStripeEditUrl(), ['target' => '_blank', 'data' => ['icon' => 'external']]),
            'stripeStatus' => $this->getStripeStatusHtml(),
            'type' => $this->type ?? '',
            'pricePerUnit' => $this->pricePerUnit(),
            'unitPrice' => $this->unitPrice(),
            'currency' => strtoupper($this->primaryCurrency),
            'interval' => $this->interval(),
            default => parent::attributeHtml($attribute),
        };
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
     * @throws InvalidConfigException
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

    /**
     * Shortcut to get the checkout URL for the price element.
     *
     * @param string|User|false|null $customer,
     * @param string|null $successUrl,
     * @param string|null $cancelUrl,
     * @param array|null $params,
     * @return string
     * @throws InvalidConfigException
     * @throws \Throwable
     */
    public function getCheckoutUrl(
        string|User|false|null $customer = null,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        ?array $params = null,
    ): string {
        return Plugin::getInstance()->getCheckout()->getCheckoutUrl(
            [
                [
                    'price' => $this->stripeId,
                    'quantity' => 1,
                ],
            ],
            $customer,
            $successUrl,
            $cancelUrl,
            $params
        );
    }

    /**
     * Sets a list of user group IDs.
     *
     * @param array $ids
     */
    public function setUserGroupAssignmentIds(array $ids): void
    {
        $this->_userGroupAssignmentIds = $ids;
    }

    /**
     * Gets the list of user group IDs.
     */
    public function getUserGroupAssignmentIds(): array
    {
        return $this->_userGroupAssignmentIds ?? [];
    }

    /**
     * Returns a list of currently-configured user groups subscribers should be added to.
     *
     * @return UserGroup[]
     */
    public function getUserGroupAssignments(): array
    {
        if (!isset($this->_userGroupAssignments)) {
            // Do we have a list of IDs already?
            if (isset($this->_userGroupAssignmentIds)) {
                $groupIds = $this->_userGroupAssignmentIds;
            } else {
                if ($this->id) {
                    // Fetch them, if we have a source ID:
                    $groupIds = (new Query())
                        ->select(['groupId'])
                        ->from([Table::PRICES_USERGROUPS])
                        ->where(['priceId' => $this->id])
                        ->column();
                } else {
                    $groupIds = [];
                }
            }

            // Turn those IDs into models, discarding any that didn’t resolve:
            $groups = array_filter(array_map(function($id) {
                return Craft::$app->getUserGroups()->getGroupById((int)$id);
            }, $groupIds));

            // Memoize the results:
            $this->setUserGroupAssignments($groups);
        }

        return $this->_userGroupAssignments;
    }

    /**
     * Sets an array of user groups.
     *
     * @param UserGroup[] $groups
     */
    public function setUserGroupAssignments(array $groups): void
    {
        $this->_userGroupAssignments = $groups;
        $this->_userGroupAssignmentIds = array_map(fn(UserGroup $userGroup) => $userGroup->id, $groups);
    }
}
