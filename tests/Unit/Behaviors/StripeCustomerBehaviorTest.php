<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Behaviors;

use craft\elements\User;
use craft\stripe\behaviors\StripeCustomerBehavior;
use craft\stripe\tests\UnitTestCase;
use RuntimeException;
use yii\base\Component;

class StripeCustomerBehaviorTest extends UnitTestCase
{
    public function testAttachThrowsForNonUserOwner(): void
    {
        $this->expectException(RuntimeException::class);

        (new Component())->attachBehavior('stripeCustomer', StripeCustomerBehavior::class);
    }

    public function testAttachSucceedsForUserOwner(): void
    {
        $user = new User();
        $user->attachBehavior('stripeCustomer', StripeCustomerBehavior::class);

        $this->assertInstanceOf(
            StripeCustomerBehavior::class,
            $user->getBehavior('stripeCustomer')
        );
    }
}
