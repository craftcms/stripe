<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\conditions\users;

use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\stripe\behaviors\StripeCustomerBehavior;
use craft\stripe\db\Table;

/**
 * Class HasStripeCustomerConditionRule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class HasStripeCustomerConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return \Craft::t('stripe', 'Has Stripe Customer');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['hasStripeCustomer'];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $stripeCustomerQuery = (new Query())
            ->select(['email'])
            ->from(Table::CUSTOMERDATA);

        if ($this->value) {
            $query->andWhere(['users.email' => $stripeCustomerQuery]);
        } else {
            $query->andWhere(['not', ['users.email' => $stripeCustomerQuery]]);
        }
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var User|StripeCustomerBehavior $element */
        return $this->value === $element->getStripeCustomers()->isNotEmpty();
    }
}
