<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements;

use Craft;
use craft\base\Element;
use craft\elements\db\EagerLoadPlan;
use craft\elements\User;
use craft\enums\Color;
use craft\errors\SiteNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\stripe\elements\db\SubscriptionQuery;
use craft\stripe\helpers\Subscription as SubscriptionHelper;
use craft\stripe\models\Customer;
use craft\stripe\Plugin;
use craft\stripe\records\Subscription as SubscriptionRecord;
use craft\stripe\web\assets\stripecp\StripeCpAsset;
use DateTime;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Subscription element type
 *
 * @property-read Product[]|null $products the products in this subscription
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Subscription extends Element
{
    // Constants
    // -------------------------------------------------------------------------

    /**
     * Craft Statuses
     */
    public const STATUS_LIVE = 'live';
    public const STATUS_STRIPE_SCHEDULED = 'stripeScheduled';
    public const STATUS_STRIPE_CANCELED = 'stripeCanceled';

    /**
     * Stripe Statuses
     */
    public const STRIPE_STATUS_ACTIVE = 'active';
    public const STRIPE_STATUS_SCHEDULED = 'scheduled';
    public const STRIPE_STATUS_CANCELED = 'canceled';

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
    public ?array $prices = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $canceledAt = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $currentPeriodEnd = null;

    /**
     * @var string|null
     */
    public ?string $customerId = null;

    /**
     * @var int|null ID of the subscriber’s {@see User} element (if one exists)
     */
    public ?int $userId = null;

    /**
     * @var string|null
     */
    public ?string $latestInvoiceId = null;

    /**
     * @var DateTime|null
     */
    public ?\DateTime $startDate = null;

    /**
     * @var DateTime|null
     */
    public ?\DateTime $trialStart = null;

    /**
     * @var DateTime|null
     */
    public ?\DateTime $trialEnd = null;

    /**
     * @var array|null
     */
    private ?array $_data = null;

    /**
     * @var Product[]|null Products
     * @see getProducts()
     */
    private ?array $_products = null;

    /**
     * @var Customer|null Customer
     * @see getCustomer()
     */
    private ?Customer $_customer = null;

    /**
     * @var User|null The subscriber’s User element.
     * @see getUser()
     * @see setUser()
     */
    private ?User $_user = null;

    /**
     * @var array|string[] Array of params that should be expanded when fetching Subscription from the Stripe API
     */
    public static array $expandParams = [];

    // Methods
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('stripe', 'Stripe Subscription');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('stripe', 'Stripe subscription');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('stripe', 'Stripe subscriptions');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('stripe', 'Stripe subscriptions');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'stripesubscription';
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
    public function showStatusField(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => Craft::t('app', 'Live'),
            self::STATUS_STRIPE_SCHEDULED => ['label' => Craft::t('stripe', 'Scheduled in Stripe'), 'color' => Color::Orange],
            self::STATUS_STRIPE_CANCELED => ['label' => Craft::t('stripe', 'Canceled in Stripe'), 'color' => Color::Red],
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
                self::STRIPE_STATUS_SCHEDULED => self::STATUS_STRIPE_SCHEDULED,
                self::STRIPE_STATUS_CANCELED => self::STATUS_STRIPE_CANCELED,
                default => self::STATUS_LIVE,
            };
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public static function find(): SubscriptionQuery
    {
        return Craft::createObject(SubscriptionQuery::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return Craft::$app->fields->getLayoutByType(Subscription::class);
    }

    /**
     * @inheritdoc
     */
    public function getSidebarHtml(bool $static): string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Craft::$app->getView()->registerAssetBundle(StripeCpAsset::class);
        $subscriptionCard = SubscriptionHelper::renderCardHtml($this);
        return parent::getSidebarHtml($static) . $subscriptionCard;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('stripe', 'All subscriptions'),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle === 'user') {
            $map = array_map(function(Subscription $el) {
                return [
                    'source' => $el->id,
                    'target' => $el->userId,
                ];
            }, $sourceElements);

            return [
                'elementType' => User::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
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
        unset($sortOptions['products']);

        $sortOptions['title'] = self::displayName();

        $sortOptions['stripeId'] = [
            'label' => Craft::t('stripe', 'Stripe ID'),
            'orderBy' => 'stripeId',
            'defaultDir' => SORT_DESC,
        ];

        $sortOptions['customerEmail'] = [
            'label' => Craft::t('stripe', 'Customer Email'),
            'orderBy' => '[[stripe_customerdata.email]]',
            'defaultDir' => SORT_ASC,
        ];

        return $sortOptions;
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'stripeId' => ['label' => Craft::t('stripe', 'Stripe ID')],
            'stripeEdit' => ['label' => Craft::t('stripe', 'Stripe Edit')],
            'id' => ['label' => Craft::t('app', 'ID')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
            'products' => ['label' => Craft::t('stripe', 'Products')],
            'customerEmail' => ['label' => Craft::t('stripe', 'Customer Email')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'stripeId',
            'customerEmail',
            'stripeEdit',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineCardAttributes(): array
    {
        return array_merge(parent::defineCardAttributes(), [
            'stripeId' => [
                'label' => Craft::t('stripe', 'Stripe ID'),
                'placeholder' => 'sub_xxxxxxxxxxx',
            ],
            'stripeEdit' => [
                'label' => Craft::t('stripe', 'Stripe Edit'),
                'placeholder' => Html::a('', "#", ['target' => '_blank', 'data' => ['icon' => 'external']]),
            ],
            'customerEmail' => [
                'label' => Craft::t('stripe', 'Customer Email'),
                'placeholder' => 'test@example.com',
            ],
            'products' => [
                'label' => Craft::t('stripe', 'Products'),
                'placeholder' => function() {
                    $mockup = new Product();
                    $mockup->title = Craft::t('stripe', '{type} Title', ['type' => $mockup->displayName()]);

                    return Cp::chipHtml($mockup);
                },
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
            'products' => call_user_func($attribute['placeholder']),
            default => ElementHelper::attributeHtml($attribute['placeholder'] ?? $attribute['label']),
        };
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultCardAttributes(): array
    {
        return [
            'customerEmail',
        ];
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        return parent::canView($user) || $user->can('accessPlugin-stripe');
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
        return sprintf('stripe/subscriptions/%s', $this->getCanonicalId());
    }

    /**
     * @inheritdoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('stripe/subscriptions');
    }

    /**
     * @inheritdoc
     */
    protected function crumbs(): array
    {
        return [
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('stripe/subscriptions'),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [
            'email',
        ];
    }

    /**
     * @inheritdoc
     * @noinspection PhpUnused
     */
    public function getSearchKeywords(string $attribute): string
    {
        switch ($attribute) {
            case 'email':
                return $this->getCustomer()?->email ?? '';
            default:
                return parent::getSearchKeywords($attribute);
        }
    }

    protected function safeActionMenuItems(): array
    {
        $items = parent::safeActionMenuItems();

        /** @var \Stripe\Subscription $stripeSubscription */
        $stripeSubscription = $this->getData();
        if ($this->stripeStatus !== self::STRIPE_STATUS_CANCELED && $stripeSubscription['cancel_at_period_end']) {
            $items[] = [
                'icon' => 'play',
                'label' => Craft::t('stripe', 'Resume subscription'),
                'action' => 'stripe/subscriptions/resume',
                'params' => [
                    'stripeId' => $this->stripeId,
                ],
            ];
        }

        return $items;
    }

    protected function destructiveActionMenuItems(): array
    {
        $items = parent::destructiveActionMenuItems();

        if ($this->stripeStatus !== self::STRIPE_STATUS_CANCELED) {
            $items[] = [
                'icon' => 'ban',
                'label' => Craft::t('stripe', 'Cancel Immediately'),
                'action' => 'stripe/subscriptions/cancel',
                'params' => [
                    'stripeId' => $this->stripeId,
                    'immediately' => true,
                ],
            ];


            /** @var \Stripe\Subscription $stripeSubscription */
            $stripeSubscription = $this->getData();
            if (!$stripeSubscription['cancel_at_period_end']) {
                $items[] = [
                    'icon' => 'ban',
                    'label' => Craft::t('stripe', 'Cancel at period end'),
                    'action' => 'stripe/subscriptions/cancel',
                    'params' => [
                        'stripeId' => $this->stripeId,
                    ],
                ];
            }
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = SubscriptionRecord::findOne($this->id);

            if (!$record) {
                throw new \Exception('Invalid subscription ID: ' . $this->id);
            }
        } else {
            $record = new SubscriptionRecord();
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
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'stripeEdit' => Html::a('', $this->getStripeEditUrl(), ['target' => '_blank', 'data' => ['icon' => 'external']]),
            'stripeStatus' => $this->getStripeStatusHtml(),
            'products' => Cp::elementPreviewHtml($this->getProducts()),
            'customerEmail' => $this->getCustomer()?->email ?? '',
            default => parent::attributeHtml($attribute),
        };
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        switch ($plan->handle) {
            case 'user':
                $this->setUser($elements[0]);
                break;
            default:
                parent::setEagerLoadedElements($handle, $elements, $plan);
        }
    }

    /**
     * Return URL to edit the subscription in Stripe Dashboard
     *
     * @return string
     */
    public function getStripeEditUrl(): string
    {
        return Plugin::getInstance()->stripeBaseUrl . "/subscriptions/{$this->stripeId}";
    }

    /**
     * @return string
     */
    public function getStripeStatusHtml(): string
    {
        $color = match ($this->stripeStatus) {
            'active' => 'green',
            'scheduled' => 'orange',
            'canceled' => 'red',
            default => 'blue',
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
     * Gets products that belong to this subscription
     *
     * @return Product[]|null
     */
    public function getProducts(): array|null
    {
        if (!isset($this->_products)) {
            $this->_products = Plugin::getInstance()->getProducts()->getProductsBySubscriptionId($this->stripeId);
        }

        return $this->_products;
    }

    /**
     * Gets the Prices that are part of the subscription.
     *
     * @return Price[]
     */
    public function getPrices(): array
    {
        if (!$this->prices) {
            return [];
        }

        return Price::find()
            ->stripeId($this->prices)
            ->all();
    }

    /**
     * Returns customer this subscription is related to.
     *
     * @return Customer|null
     */
    public function getCustomer(): Customer|null
    {
        return $this->_customer;
    }

    /**
     * Sets the customer for the subscription element.
     *
     * @param Customer $customer
     * @return void
     */
    public function setCustomer(Customer $customer): void
    {
        $this->_customer = $customer;
    }

    /**
     * Gets the User element corresponding to the subscriber.
     *
     * @return User|null
     * @since 1.7.x
     */
    public function getUser(): ?User
    {
        if ($this->_user) {
            return $this->_user;
        }

        if ($this->userId) {
            $user = Craft::$app->getUsers()->getUserById($this->_userId);

            $this->setUser($user);

            return $this->_user;
        }

        // Ok, we don’t have any concrete relationship to a user... let’s go via the customer:

        $customer = $this->getCustomer();

        if (!$customer) {
            return null;
        }

        return $customer->getUser();
    }

    /**
     * Sets the subscriber’s User element.
     *
     * @param User $user
     * @since 1.7.x
     */
    public function setUser(User $user): void
    {
        $this->userId = $user->id;
        $this->_user = $user;
    }

    /**
     * Returns the URL to update the subscription in the billing portal.
     *
     * @param string|null $returnUrl
     * @param array $params
     * @return string|null
     * @throws \Throwable
     * @throws SiteNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function getBillingPortalSessionUpdateUrl(
        ?string $returnUrl = null,
        array $params = [],
    ): ?string {
        $returnUrl = $returnUrl ? UrlHelper::siteUrl($returnUrl) : UrlHelper::siteUrl();
        if ($this->status !== self::STATUS_LIVE) {
            return '';
        }

        $params = ArrayHelper::merge([
            'flow_data' => [
                'type' => 'subscription_update',
                'subscription_update' => [
                    'subscription' => $this->stripeId,
                ],
            ],
        ], $params);

        return Plugin::getInstance()->getBillingPortal()->getSessionUrl($this->customerId, null, $returnUrl, $params);
    }

    /**
     * Returns the URL to cancel the subscription in the billing portal.
     *
     * @param string|null $returnUrl
     * @param array $params
     * @return string|null
     * @throws Exception
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @throws \Throwable
     */
    public function getBillingPortalSessionCancelUrl(
        ?string $returnUrl = null,
        array $params = [],
    ): ?string {
        $returnUrl = $returnUrl ? UrlHelper::siteUrl($returnUrl) : UrlHelper::siteUrl();
        if ($this->status !== self::STATUS_LIVE) {
            return '';
        }

        $params = ArrayHelper::merge([
            'flow_data' => [
                'type' => 'subscription_cancel',
                'subscription_cancel' => [
                    'subscription' => $this->stripeId,
                ],
            ],
        ], $params);

        return Plugin::getInstance()->getBillingPortal()->getSessionUrl($this->customerId, null, $returnUrl, $params);
    }
}
