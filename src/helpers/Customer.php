<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\helpers;

use Craft;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\stripe\Plugin;

/**
 * Stipe Customer Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Customer
{
    /**
     * Returns chip or link for the customer.
     * If a user with the same email address as the customer id is found - return element chip.
     * Otherwise - link to the customer in stripe
     *
     *
     * @param string $id
     * @return string
     */
    public static function getCustomerLink(string $id): string
    {
        $customer = Plugin::getInstance()->getCustomers()->getCustomerByStripeId($id);

        // if we can't find the customer - just return the id
        if ($customer === null) {
            return $id;
        }

        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($customer->email);

        // if we have the customer but not the user - return a link to view the customer directly in stripe
        if ($user === null) {
            return Html::a($id, self::getStripeEditUrl($id), ['target' => '_blank']);
        }

        // if we found a matching user - return element chip
        return Cp::elementChipHtml($user, ['size' => Cp::CHIP_SIZE_SMALL]);

    }

    /**
     * Return URL to edit the customer in Stripe Dashboard by the passed customer id.
     * 
     * @param $id
     * @return string
     */
    public static function getStripeEditUrl($id): string
    {
        return Plugin::getInstance()->stripeBaseUrl . "/customers/{$id}";
    }
}
