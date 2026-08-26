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
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;
use yii\web\ForbiddenHttpException;

class SubscriptionsControllerTest extends TestCase
{
    public function testRenderMetaCardHtmlReturnsSubscriptionCard(): void
    {
        $this->loginAsAdmin();

        Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription(
            StripeApiObjectFactory::subscription('sub_controller_test', ['customer' => 'cus_unresolvable'])
        );
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

        Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription(
            StripeApiObjectFactory::subscription('sub_to_resume')
        );

        $subscriptions = $this->partialMockComponent('subscriptions', Subscriptions::class, ['resumeSubscriptionByStripeId']);
        $subscriptions->method('resumeSubscriptionByStripeId')->willReturn(true);

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

        Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription(
            StripeApiObjectFactory::subscription('sub_to_cancel')
        );

        $subscriptions = $this->partialMockComponent('subscriptions', Subscriptions::class, ['cancelSubscriptionByStripeId']);
        $subscriptions->method('cancelSubscriptionByStripeId')->willReturn(true);

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
