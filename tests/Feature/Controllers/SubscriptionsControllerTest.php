<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Controllers;

use Craft;
use craft\stripe\controllers\SubscriptionsController;
use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use craft\stripe\services\Subscriptions;
use craft\stripe\tests\TestCase;
use Stripe\Subscription as StripeSubscription;
use yii\web\ForbiddenHttpException;

class SubscriptionsControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Plugin::getInstance()->set('subscriptions', Subscriptions::class);

        parent::tearDown();
    }

    public function testRenderMetaCardHtmlReturnsSubscriptionCard(): void
    {
        $this->loginAsAdmin();

        Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription(StripeSubscription::constructFrom([
            'id' => 'sub_controller_test',
            'status' => 'active',
            'customer' => 'cus_unresolvable',
            'items' => ['data' => []],
            'current_period_start' => 1700000000,
            'current_period_end' => 1702592000,
            'cancel_at_period_end' => false,
            'cancel_at' => null,
            'canceled_at' => null,
            'ended_at' => null,
            'discounts' => [],
            'metadata' => [],
            'created' => 1700000000,
        ]));
        $subscription = Subscription::find()->stripeId('sub_controller_test')->one();

        Craft::$app->getRequest()->setQueryParams(['id' => $subscription->id]);

        $controller = new SubscriptionsController('subscriptions', Craft::$app);
        $html = $controller->runAction('render-meta-card-html');

        $this->assertIsString($html);
        $this->assertStringContainsString($subscription->getStripeEditUrl(), $html);
    }

    public function testRenderMetaCardHtmlRequiresPermission(): void
    {
        $this->expectException(ForbiddenHttpException::class);

        $controller = new SubscriptionsController('subscriptions', Craft::$app);
        $controller->runAction('render-meta-card-html');
    }

    public function testResumeReturnsFailureWhenSubscriptionNotFound(): void
    {
        $this->loginAsAdmin();
        $this->asPost(['stripeId' => 'sub_does_not_exist']);

        $controller = new SubscriptionsController('subscriptions', Craft::$app);
        $controller->enableCsrfValidation = false;
        $response = $controller->runAction('resume');

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testResumeSucceedsWhenServiceReportsSuccess(): void
    {
        $this->loginAsAdmin();

        Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription(StripeSubscription::constructFrom([
            'id' => 'sub_to_resume',
            'status' => 'active',
            'items' => ['data' => []],
        ]));

        $subscriptions = $this->getMockBuilder(Subscriptions::class)
            ->onlyMethods(['resumeSubscriptionByStripeId'])
            ->getMock();
        $subscriptions->method('resumeSubscriptionByStripeId')->willReturn(true);
        Plugin::getInstance()->set('subscriptions', $subscriptions);

        $this->asPost(['stripeId' => 'sub_to_resume']);

        $controller = new SubscriptionsController('subscriptions', Craft::$app);
        $controller->enableCsrfValidation = false;
        $response = $controller->runAction('resume');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCancelReturnsFailureWhenSubscriptionNotFound(): void
    {
        $this->loginAsAdmin();
        $this->asPost(['stripeId' => 'sub_does_not_exist']);

        $controller = new SubscriptionsController('subscriptions', Craft::$app);
        $controller->enableCsrfValidation = false;
        $response = $controller->runAction('cancel');

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCancelSucceedsWhenServiceReportsSuccess(): void
    {
        $this->loginAsAdmin();

        Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription(StripeSubscription::constructFrom([
            'id' => 'sub_to_cancel',
            'status' => 'active',
            'items' => ['data' => []],
        ]));

        $subscriptions = $this->getMockBuilder(Subscriptions::class)
            ->onlyMethods(['cancelSubscriptionByStripeId'])
            ->getMock();
        $subscriptions->method('cancelSubscriptionByStripeId')->willReturn(true);
        Plugin::getInstance()->set('subscriptions', $subscriptions);

        $this->asPost(['stripeId' => 'sub_to_cancel', 'immediately' => true]);

        $controller = new SubscriptionsController('subscriptions', Craft::$app);
        $controller->enableCsrfValidation = false;
        $response = $controller->runAction('cancel');

        $this->assertSame(200, $response->getStatusCode());
    }

    private function asPost(array $bodyParams): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Craft::$app->getRequest()->setAcceptableContentTypes(['application/json' => ['q' => 1]]);
        Craft::$app->getRequest()->setBodyParams($bodyParams);
    }
}
