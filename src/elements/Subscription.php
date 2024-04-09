<?php

namespace craft\stripe\elements;

use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\stripe\Plugin;
use craft\stripe\elements\db\SubscriptionQuery;
use craft\stripe\helpers\Subscription as SubscriptionHelper;
use craft\stripe\records\Subscription as SubscriptionRecord;
use craft\stripe\web\assets\stripecp\StripeCpAsset;
use yii\helpers\Html as HtmlHelper;

/**
 * Subscription element type
 *
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
    private ?array $_data = null;

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
        return Craft::t('stripe', 'stripe subscription');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('stripe', 'Stripe Subscriptions');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('stripe', 'stripe subscriptions');
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
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => Craft::t('app', 'Live'),
            self::STATUS_STRIPE_SCHEDULED => ['label' => Craft::t('stripe', 'Scheduled in Stripe'), 'color' => 'orange'],
            self::STATUS_STRIPE_CANCELED => ['label' => Craft::t('stripe', 'Canceled in Stripe'), 'color' => 'red'],
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
    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(SubscriptionQuery::class, [static::class]);
    }

//    /**
//     * @inheritdoc
//     */
//    public static function createCondition(): ElementConditionInterface
//    {
//        return Craft::createObject(ProductCondition::class, [static::class]);
//    }

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
        return $subscriptionCard . parent::getSidebarHtml($static);
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
            'orderBy' => 'stripe_subscriptiondata.stripeId',
            'defaultDir' => SORT_DESC,
        ];

        $sortOptions['stripeStatus'] = [
            'label' => Craft::t('stripe', 'Stripe Status'),
            'orderBy' => 'stripe_subscriptiondata.stripeStatus',
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

//    /**
//     * @inheritdoc
//     */
//    public function getUriFormat(): ?string
//    {
//        return Plugin::getInstance()->getSettings()->productUriFormat;
//    }

//    /**
//     * @inheritdoc
//     */
//    protected function previewTargets(): array
//    {
//        $previewTargets = [];
//        $url = $this->getUrl();
//        if ($url) {
//            $previewTargets[] = [
//                'label' => Craft::t('app', 'Primary {type} page', [
//                    'type' => self::lowerDisplayName(),
//                ]),
//                'url' => $url,
//            ];
//        }
//        return $previewTargets;
//    }

//    /**
//     * @inheritdoc
//     */
//    protected function route(): array|string|null
//    {
//        if (!$this->previewing && $this->getStatus() != self::STATUS_LIVE) {
//            return null;
//        }
//
//        $settings = Plugin::getInstance()->getSettings();
//
//        if ($settings->productUriFormat) {
//            return [
//                'templates/render', [
//                    'template' => $settings->productTemplate,
//                    'variables' => [
//                        'product' => $this,
//                    ],
//                ],
//            ];
//        }
//
//        return null;
//    }

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('viewSubscriptions');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('saveSubscriptions');
    }

//    public function canDuplicate(User $user): bool
//    {
//        if (parent::canDuplicate($user)) {
//            return true;
//        }
//        // todo: implement user permissions
//        return $user->can('saveSubscriptions');
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
//        return $user->can('deleteSubscriptions');
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
        switch ($attribute) {
            case 'stripeEdit':
                return HtmlHelper::a('', $this->getStripeEditUrl(), ['target' => '_blank', 'data' => ['icon' => 'external']]);
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
     * Return URL to edit the subscription in Stripe Dashboard
     *
     * @return string
     */
    public function getStripeEditUrl(): string
    {
        $dashboardUrl = Plugin::getInstance()->dashboardUrl;
        $mode = Plugin::getInstance()->stripeMode;
        return "{$dashboardUrl}/{$mode}/subscriptions/{$this->stripeId}";
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
}
