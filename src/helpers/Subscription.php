<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\helpers;

use Craft;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use craft\stripe\elements\Subscription as SubscriptionElement;

/**
 * Stipe Subscription Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Subscription
{
    /**
     * @return string
     */
    public static function renderCardHtml(SubscriptionElement $subscription): string
    {
        $formatter = Craft::$app->getFormatter();

        /** @var \Stripe\Subscription $stripeSubscription */
        $stripeSubscription = $subscription->getData();

        $properties = [
            'customer',
            'currentPeriod',
            'cancelAtPeriodEnd',
            'discount',
            'cancelAt',
            'canceledAt',
            'endedAt',
            'products',
        ];

        $title = Html::tag('h3', Html::encode($subscription->title), [
            'class' => 'pec-title',
        ]);

        $externalLink = Html::tag('div', '&nbsp;', [
            'class' => 'pec-external-icon',
            'data' => [
                'icon' => 'external',
            ],
        ]);

        $cardHeader = Html::a($title . $externalLink, $subscription->getStripeEditUrl(), [
            'style' => '',
            'class' => 'pec-header',
            'target' => '_blank',
            'title' => Craft::t('stripe', 'Open in Stripe'),
        ]);

        $hr = Html::tag('hr', '', [
            'class' => '',
        ]);

        $meta = [];

        $meta[Craft::t('stripe', 'Status')] = $subscription->getStripeStatusHtml();
        $meta[Craft::t('stripe', 'Stripe ID')] =
            Cp::renderTemplate('_includes/forms/copytext.twig', [
                'id' => "stripe-subscription-stripeId",
                'class' => ['code', 'text', 'fullwidth'],
                'value' => (string)$subscription->stripeId,
            ]);

        if (count($stripeSubscription) > 0) {
            // Customer/Subscriber
            $meta[Craft::t('stripe', 'Customer')] = Customer::getCustomerLink($stripeSubscription['customer']);

            // Billing period
            $meta[Craft::t('stripe', 'Current period')] =
                $formatter->asDate($stripeSubscription['current_period_start']) .
                ' to ' .
                $formatter->asDate($stripeSubscription['current_period_end']);

            // Cancellation + Expiry
            if ($stripeSubscription['cancel_at'] !== null) {
                $meta[Craft::t('stripe', 'Cancel at')] =
                    $formatter->asDatetime($stripeSubscription['cancel_at'], Formatter::FORMAT_WIDTH_SHORT);
            }

            if ($stripeSubscription['canceled_at'] !== null) {
                $meta[Craft::t('stripe', 'Canceled at')] =
                    $formatter->asDatetime($stripeSubscription['canceled_at'], Formatter::FORMAT_WIDTH_SHORT);

                $meta[Craft::t('stripe', 'Cancel at period end')] =
                    $stripeSubscription['cancel_at_period_end'] ?
                        Craft::t('stripe', 'Yes') :
                        Craft::t('stripe', 'No');
            }

            if ($stripeSubscription['ended_at'] !== null) {
                $meta[Craft::t('stripe', 'Ended at')] =
                    $formatter->asDatetime($stripeSubscription['ended_at'], Formatter::FORMAT_WIDTH_SHORT);
            }

            // Applied discount(s)
            $meta[Craft::t('stripe', 'Discounts')] = collect($stripeSubscription['discounts'])
                ->filter()
                ->join(', ');

            // Products
            $meta[Craft::t('stripe', 'Products')] = Html::ul(array_filter($subscription->getProducts()), [
                'item' => function($product, $index) {
                    return Html::tag('li', Cp::elementChipHtml($product, ['hyperlink' => true]));
                },
            ]);
        }

        // Metadata
        $meta[Craft::t('stripe', 'Metadata')] =
            Html::beginTag('div', ['class' => 'pec-metadata']) .
            collect($stripeSubscription['metadata'])
            ->map(function($value, $key) {
                return Html::beginTag('div', ['class' => 'fullwidth']) .
                    Html::tag('em', $key . ': ') .
                    $value .
                    Html::endTag('div');
            })
            ->join(' ') .
            Html::endTag('div');

        $meta[Craft::t('app', 'Created at')] = $formatter->asDatetime($stripeSubscription['created'], Formatter::FORMAT_WIDTH_SHORT);

        $spinner = Html::tag('div', '', [
            'class' => 'spinner',
            'hx' => [
                'indicator',
            ],
        ]);

        $dateCreated = DateTimeHelper::toDateTime($stripeSubscription['created']);
        $now = new \DateTime();
        $diff = $now->diff($dateCreated);
        $duration = DateTimeHelper::humanDuration($diff, false);
        $footer = Html::tag('div', 'Created ' . $duration . ' ago.' . $spinner, [
            'class' => 'pec-footer',
        ]);

        $metadataHtml = Cp::metadataHtml($meta);

        return Html::tag('div', $cardHeader . $hr . $metadataHtml . $footer, [
            'class' => 'meta proxy-element-card',
            'id' => 'pec-' . $subscription->id,
            'hx' => [
                'get' => UrlHelper::actionUrl('stripe/subscriptions/render-meta-card-html', [
                    'id' => $subscription->id,
                ]),
                'swap' => 'outerHTML',
                'trigger' => 'every 30s',
            ],
        ]);
    }
}
