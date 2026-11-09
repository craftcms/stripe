<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements\Conditions;

use CraftCms\Cms\Condition\BaseLightswitchConditionRule;
use CraftCms\Cms\Condition\Contracts\ConditionInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\User\Conditions\UserCondition;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Database\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Override;

use function CraftCms\Cms\t;

/**
 * Filters users by whether their email belongs to a Stripe customer.
 *
 * @since 3.0.0
 */
class HasStripeCustomerConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public static function isSelectableForCondition(ConditionInterface $condition): bool
    {
        return $condition instanceof UserCondition;
    }

    #[Override]
    public function getLabel(): string
    {
        return t('Has Stripe Customer', category: 'stripe');
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        $customerEmails = DB::table(Table::CUSTOMERDATA)->select('email');

        if ($this->value) {
            $query->whereIn('users.email', $customerEmails);
        } else {
            $query->whereNotIn('users.email', $customerEmails);
        }
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var User $element */
        return $this->value === (app(Customers::class)->getCustomersByEmail($element->email) !== []);
    }
}
