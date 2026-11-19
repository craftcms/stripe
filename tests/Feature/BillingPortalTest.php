<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\Stripe\BillingPortal;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Events\BillingPortalSessionCreating;
use CraftCms\Stripe\Plugin;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    app(Customers::class)->syncAllCustomers();
});

it('doesn’t start a session without a logged-in user', function () {
    expect(app(BillingPortal::class)->getSessionUrl('cus_alice'))->toBeNull();
});

it('doesn’t start a session for a customer the current user doesn’t own', function () {
    Edition::set(Edition::Pro);
    actingAs(UserModel::factory()->createElement(['email' => 'bob@example.com']));

    expect(app(BillingPortal::class)->getSessionUrl('cus_alice'))->toBeNull()
        ->and($this->stripe->requestsTo('POST', '/v1/billing_portal/sessions'))->toBe([]);
});

it('doesn’t start a session for an unknown customer', function () {
    expect(app(BillingPortal::class)->getCustomerBillingPortalSessionUrl('cus_missing'))->toBeNull();
});

it('starts a session with the customer, configuration, return URL and extra params', function () {
    $url = app(BillingPortal::class)->getCustomerBillingPortalSessionUrl('cus_alice', 'bpc_test', 'account/billing', [
        'flow_data' => ['type' => 'payment_method_update'],
    ]);

    expect($url)->toStartWith('https://billing.stripe.test/p/session/')
        ->and($this->stripe->requestsTo('POST', '/v1/billing_portal/sessions')[0])->toBe([
            'flow_data' => ['type' => 'payment_method_update'],
            'customer' => 'cus_alice',
            'return_url' => Url::siteUrl('account/billing'),
            'configuration' => 'bpc_test',
        ]);
});

it('lets listeners change the session params', function () {
    Event::listen(BillingPortalSessionCreating::class, function (BillingPortalSessionCreating $event) {
        $event->params['locale'] = 'auto';
    });

    app(BillingPortal::class)->getCustomerBillingPortalSessionUrl('cus_alice');

    expect($this->stripe->requestsTo('POST', '/v1/billing_portal/sessions')[0]['locale'])->toBe('auto');
});

it('returns an empty string when Stripe fails', function () {
    // The fake Stripe API rejects keys that don’t start with `sk_test_`
    Plugin::getInstance()->getSettings()->secretKey = 'sk_live_rejected';

    expect(app(BillingPortal::class)->getCustomerBillingPortalSessionUrl('cus_alice'))->toBe('');
});
