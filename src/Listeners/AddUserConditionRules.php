<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Listeners;

use CraftCms\Cms\Condition\Events\ConditionRulesResolving;
use CraftCms\Cms\User\Conditions\UserCondition;
use CraftCms\Stripe\Elements\Conditions\HasStripeCustomerConditionRule;

/**
 * Adds the “Has Stripe Customer” rule to user conditions.
 *
 * @since 3.0.0
 */
class AddUserConditionRules
{
    public function handle(ConditionRulesResolving $event): void
    {
        if ($event->condition instanceof UserCondition) {
            $event->conditionRules[] = HasStripeCustomerConditionRule::class;
        }
    }
}
