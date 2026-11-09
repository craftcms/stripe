<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\User\Conditions\UserCondition;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\Stripe\Elements\Conditions\CurrencyConditionRule;
use CraftCms\Stripe\Elements\Conditions\HasStripeCustomerConditionRule;
use CraftCms\Stripe\Elements\Conditions\PriceCondition;
use CraftCms\Stripe\Elements\Conditions\PriceTypeConditionRule;
use CraftCms\Stripe\Elements\Conditions\ProductCondition;
use CraftCms\Stripe\Elements\Conditions\ProductPriceTypeConditionRule;
use CraftCms\Stripe\Elements\Conditions\StripeStatusConditionRule;
use CraftCms\Stripe\Elements\Conditions\UnitAmountConditionRule;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;

beforeEach(function () {
    $this->stripe->state['products']['prod_ebook']['active'] = false;
    $this->artisan('stripe:sync')->assertSuccessful();
});

/**
 * Returns the Stripe IDs a condition’s query selects, after checking that matchElement() agrees.
 *
 * @param  class-string<Product|Price>  $elementType
 * @param  array<string, mixed>  $rule
 * @return list<string>
 */
function stripeIdsMatching(string $elementType, string $conditionClass, array $rule): array
{
    $condition = Conditions::createCondition(['class' => $conditionClass, 'elementType' => $elementType, 'conditionRules' => [$rule]]);
    $query = $elementType::find()->status(null)->orderBy('id');
    $condition->modifyQuery($query);
    $queried = $query->collect()->pluck('stripeId')->all();

    $matched = $elementType::find()->status(null)->orderBy('id')->collect()
        ->filter(fn ($element) => $condition->matchElement($element))
        ->pluck('stripeId')->values()->all();

    expect($matched)->toBe($queried);

    return $queried;
}

it('filters prices', function (array $rule, array $expected) {
    expect(stripeIdsMatching(Price::class, PriceCondition::class, $rule))->toEqualCanonicalizing($expected);
})->with([
    'one-time' => [['class' => PriceTypeConditionRule::class, 'values' => ['one_time']], ['price_ebook']],
    'unit amount ≥ 25' => [['class' => UnitAmountConditionRule::class, 'operator' => '>=', 'value' => '25'], ['price_basic_yearly', 'price_pro_monthly']],
    'currency' => [['class' => CurrencyConditionRule::class, 'operator' => '=', 'value' => 'eur'], []],
]);

it('filters products', function (array $rule, array $expected) {
    expect(stripeIdsMatching(Product::class, ProductCondition::class, $rule))->toEqualCanonicalizing($expected);
})->with([
    'archived in Stripe' => [['class' => StripeStatusConditionRule::class, 'values' => ['archived']], ['prod_ebook']],
    'with a recurring price' => [['class' => ProductPriceTypeConditionRule::class, 'values' => ['recurring']], ['prod_basic', 'prod_pro']],
]);

it('filters users by whether they’re Stripe customers', function (bool $value, string $expected) {
    Edition::set(Edition::Pro);
    UserModel::factory()->createElement(['email' => 'alice@example.com']);

    $condition = Conditions::createCondition(['class' => UserCondition::class, 'elementType' => User::class, 'conditionRules' => [
        ['class' => HasStripeCustomerConditionRule::class, 'value' => $value],
    ]]);

    expect($condition->getSelectableConditionRules())->toHaveKey(HasStripeCustomerConditionRule::class);

    $query = User::find()->status(null);
    $condition->modifyQuery($query);
    $users = User::find()->status(null)->collect();

    expect($query->collect()->pluck('email')->all())->toBe([$expected])
        ->and($users->filter(fn (User $user) => $condition->matchElement($user))->pluck('email')->values()->all())->toBe([$expected]);
})->with([
    'customers' => [true, 'alice@example.com'],
    'not customers' => [false, 'support@craftcms.com'],
]);
