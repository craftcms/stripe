<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\stripe\base;

use craft\base\Model as BaseModel;
use craft\helpers\Json;

/**
 * Stripe base model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Model extends BaseModel
{
    /**
     * @var string|null Stripe ID
     */
    public ?string $stripeId = null;

    /**
     * @var array|null
     */
    private ?array $_data = null;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $attributes = parent::attributes();
        $attributes[] = 'data';
        return $attributes;
    }

    /**
     * @param string|array|null $value
     * @return void
     */
    public function setData(string|array|null $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        $this->_data = $value;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->_data ?? [];
    }
}
