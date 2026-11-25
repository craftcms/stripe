<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Http\Requests\TableRequest;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Models\Invoice;
use CraftCms\Stripe\Support\InvoiceTable;

use function CraftCms\Cms\t;

/**
 * Lists the synced invoices.
 *
 * @since 2.0.0
 */
class InvoicesController
{
    public function index(TableRequest $request, Api $api): CpScreenResponse
    {
        // Newest first, unless a sortable column was picked
        $sort = Arr::get($request->sort(), '0.field');
        $sortable = in_array($sort, ['number', 'customerEmail', 'created'], true);
        $orderBy = $sortable ? $sort : 'created';
        $direction = $sortable && Arr::get($request->sort(), '0.direction') === 'asc' ? 'asc' : 'desc';

        $query = Invoice::query()->orderBy($orderBy, $direction);

        if ($search = $request->search()) {
            $query->where(fn ($query) => $query
                ->whereLike('number', "%$search%")
                ->orWhereLike('customerEmail', "%$search%"));
        }

        $pageParam = Cms::config()->getPageTriggerParam();
        $paginator = $query->paginate(perPage: $request->limit(), pageName: $pageParam, page: $request->page());
        $paginator->appends(request()->except($pageParam));

        $table = Table::make('invoices')
            ->columns(InvoiceTable::columns())
            ->rows(InvoiceTable::rows($paginator->items()))
            ->pagination(Arr::only($paginator->toArray(), ['total', 'per_page', 'current_page', 'last_page', 'next_page_url', 'prev_page_url', 'from', 'to']))
            ->searchable()
            ->emptyMessage(t('No invoices exist yet.', category: 'stripe'))
            ->createAction(t('New invoice', category: 'stripe'), $api->dashboardUrl('invoices/create'))
            ->createActionInPageHeader();

        return new CpScreenResponse()
            ->title(t('Invoices', category: 'stripe'))
            ->selectedSubnavItem('stripe/invoices')
            ->ui(Ui::make([$table]));
    }
}
