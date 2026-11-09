<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\controllers\EditUserTrait;
use craft\elements\User;
use craft\web\CpScreenResponseBehavior;
use yii\web\Response;

if (!class_exists(\CraftCms\Cms\Http\Responses\CpScreenResponse::class)) {
    /**
     * Renders the Stripe user screen on Craft 5.
     *
     * @internal
     */
    trait RendersCustomerScreen
    {
        use EditUserTrait;

        private function customerUser(?int $userId): User
        {
            return $this->editedUser($userId);
        }

        private function asCustomerScreen(User $user, string $template, array $variables): Response
        {
            /** @var Response|CpScreenResponseBehavior $response */
            $response = $this->asEditUserScreen($user, 'stripe');
            $response->contentTemplate($template, $variables);

            return $response;
        }
    }
} else {
    /**
     * Renders the Stripe user screen on Craft 6, where `craft\controllers\EditUserTrait`
     * is the native controller trait and can’t be used by a Yii controller.
     *
     * @internal
     */
    trait RendersCustomerScreen
    {
        private function customerUser(?int $userId): User
        {
            return self::editUserScreen()->editedUser($userId);
        }

        private function asCustomerScreen(User $user, string $template, array $variables): Response
        {
            $screen = self::editUserScreen()
                ->asEditUserScreen($user, 'stripe')
                ->contentTemplate($template, $variables);

            return Craft::$app->getResponse()->setIlluminateResponse($screen->toResponse(request()));
        }

        private static function editUserScreen(): object
        {
            return new class() {
                use \CraftCms\Cms\Http\Controllers\Users\EditUserTrait {
                    editedUser as public;
                    asEditUserScreen as public;
                }
            };
        }
    }
}
