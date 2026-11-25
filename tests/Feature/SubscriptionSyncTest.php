<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\Support\Facades\Drafts;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\FieldLayouts;
use CraftCms\Stripe\Plugin;
use CraftCms\Stripe\Subscriptions;
use Stripe\Subscription as StripeSubscription;

it('creates a subscription element, then updates it instead of adding another', function () {
    $source = $this->stripe->state['subscriptions']['sub_alice'];

    expect(app(Subscriptions::class)->createOrUpdateSubscription(StripeSubscription::constructFrom($source)))->toBeTrue()
        ->and(Subscription::find()->stripeId('sub_alice')->one()->stripeStatus)->toBe('active');

    app(Subscriptions::class)->createOrUpdateSubscription(StripeSubscription::constructFrom(['status' => 'canceled'] + $source));

    $elements = Subscription::find()->stripeId('sub_alice')->status(null)->all();
    expect($elements)->toHaveCount(1)
        ->and($elements[0]->stripeStatus)->toBe('canceled');
});

it('keeps the checkout draft’s field values when the subscription was synced first', function () {
    $field = Fields::createField(['type' => PlainText::class, 'name' => 'Company', 'handle' => 'company']);
    expect(Fields::saveField($field))->toBeTrue();
    app(FieldLayouts::class)->saveFieldLayout(Subscription::class, Fields::createLayout([
        'tabs' => [['name' => 'Content', 'elements' => [['type' => CustomField::class, 'fieldUid' => $field->uid]]]],
    ]));

    $source = StripeSubscription::constructFrom($this->stripe->state['subscriptions']['sub_alice']);
    // `customer.subscription.updated` was handled before `.created`
    app(Subscriptions::class)->createOrUpdateSubscription($source);

    $draft = new Subscription(['title' => 'Checkout']);
    $draft->setFieldValue('company', 'Acme');
    expect(Drafts::saveElementAsDraft($draft, markAsSaved: false))->toBeTrue();

    expect(app(Subscriptions::class)->createOrUpdateSubscription($source, $draft))->toBeTrue();

    $elements = Subscription::find()->stripeId('sub_alice')->status(null)->all();
    expect($elements)->toHaveCount(1)
        ->and($elements[0]->getFieldValue('company'))->toBe('Acme')
        ->and(Subscription::find()->uid($draft->uid)->drafts()->status(null)->exists())->toBeFalse();
});

it('deletes a subscription by its Stripe ID', function () {
    app(Subscriptions::class)->syncAllSubscriptions();
    app(Subscriptions::class)->deleteSubscriptionByStripeId('sub_alice');

    expect(Subscription::find()->stripeId('sub_alice')->status(null)->exists())->toBeFalse();
});

it('creates a user for the customer’s email only when createUserIfMissing is on', function (bool $enabled) {
    Edition::set(Edition::Pro);
    Plugin::getInstance()->getSettings()->createUserIfMissing = $enabled;

    app(Subscriptions::class)->createOrUpdateSubscription(StripeSubscription::constructFrom($this->stripe->state['subscriptions']['sub_alice']));

    expect(User::find()->email('alice@example.com')->status(null)->exists())->toBe($enabled);
})->with([
    'on' => true,
    'off' => false,
]);

it('deletes the elements of subscriptions Stripe no longer has', function () {
    app(Subscriptions::class)->createOrUpdateSubscription(StripeSubscription::constructFrom(['id' => 'sub_gone'] + $this->stripe->state['subscriptions']['sub_alice']));
    app(Subscriptions::class)->syncAllSubscriptions();

    expect(Subscription::find()->status(null)->collect()->pluck('stripeId')->all())->toBe(['sub_alice']);
});
