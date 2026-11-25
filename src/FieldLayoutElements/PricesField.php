<?php

declare(strict_types=1);

namespace CraftCms\Stripe\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Enums\ElementIndexViewMode;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Stripe\Elements\Product;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

/**
 * Lists a product’s prices in its edit screen. Prices are managed in Stripe, so they can’t be added or sorted here.
 *
 * @since 1.0.0
 */
class PricesField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'prices';

    #[Override]
    public function hasCustomWidth(): bool
    {
        return false;
    }

    #[Override]
    protected function uiControl(FieldLayoutElementContext $context): ?Control
    {
        $product = $context->element;

        if (! $product instanceof Product) {
            throw new InvalidArgumentException('PricesField can only be used in Stripe product field layouts.');
        }

        return $product->getPriceManager()->uiControl($this->attribute(), $product, 'index', $this->managerConfig());
    }

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Prices', category: 'stripe');
    }

    /**
     * @return array<string, mixed>
     */
    private function managerConfig(): array
    {
        return [
            'canCreate' => false,
            'canPaste' => false,
            'sortable' => false,
            'allowedViewModes' => [ElementIndexViewMode::Cards, ElementIndexViewMode::Table],
            'pageSize' => 50,
        ];
    }
}
