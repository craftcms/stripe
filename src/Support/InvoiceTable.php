<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Support;

use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Stripe\Models\Invoice;

use function CraftCms\Cms\t;

/**
 * The columns and rows of invoice tables, on the Invoices screen and the user screen.
 *
 * @since 3.0.0
 */
class InvoiceTable
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            ['key' => 'number', 'label' => t('Number', category: 'stripe'), 'sortable' => true],
            ['key' => 'amount', 'label' => t('Amount', category: 'stripe')],
            ['key' => 'status', 'label' => t('Status', category: 'stripe')],
            ['key' => 'customerEmail', 'label' => t('Customer Email', category: 'stripe'), 'sortable' => true],
            ['key' => 'due', 'label' => t('Due Date', category: 'stripe')],
            ['key' => 'created', 'label' => t('Created', category: 'stripe'), 'sortable' => true],
        ];
    }

    /**
     * @param  iterable<Invoice>  $invoices
     * @return array<int, array<string, mixed>>
     */
    public static function rows(iterable $invoices): array
    {
        $formatter = I18N::getFormatter();
        $rows = [];

        foreach ($invoices as $invoice) {
            $data = $invoice->getData();
            $amount = PriceFormatter::toDecimal($data['total'] ?? 0, $data['currency'] ?? 'usd');

            $rows[] = [
                'id' => $invoice->stripeId,
                'number' => ['label' => $data['number'] ?? t('Draft', category: 'stripe'), 'url' => $invoice->getStripeEditUrl(), 'target' => '_blank'],
                'amount' => $formatter->asCurrency($amount, strtoupper($data['currency'] ?? 'usd')),
                'status' => $data['status'] ?? '',
                'customerEmail' => $data['customer_email'] ?? '',
                'due' => isset($data['due_date']) ? $formatter->asDate($data['due_date'], 'short') : '',
                'created' => isset($data['created']) ? $formatter->asDateTime($data['created'], 'short') : '',
            ];
        }

        return $rows;
    }
}
