<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements\Concerns;

use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;

/**
 * The Stripe API object an element was synced from.
 *
 * @since 2.0.0
 */
trait HasStripeData
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    /**
     * @param  string|array<string, mixed>|null  $value
     */
    public function setData(string|array|null $value): void
    {
        $this->data = is_string($value) ? Json::decodeIfJson($value) : $value;
    }

    /**
     * Returns the Stripe API object the element was last synced from.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function metadataHtml(array $metadata): string|false
    {
        if ($metadata === []) {
            return false;
        }

        return implode('', array_map(
            fn (string $key, mixed $value) => Html::tag('div', Html::tag('em', e($key).': ').e((string) $value)),
            array_keys($metadata),
            $metadata,
        ));
    }
}
