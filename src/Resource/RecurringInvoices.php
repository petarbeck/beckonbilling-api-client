<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\RecurringInvoice;

/**
 * Recurring invoices - `/api/v1/recurring-invoices` (feature: `recurring_invoices`).
 *
 * Generation is automatic: the portal's automation agent runs each due template
 * once a day, at 08:00 in the ORGANISATION's own timezone. There is no generate
 * endpoint in the public API.
 *
 * @method RecurringInvoice get(string $id, array $options = [])
 * @method RecurringInvoice create(array $data, array $options = [])
 * @method RecurringInvoice update(string $id, array $data, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<RecurringInvoice> list(array $query = [], array $options = [])
 * @method \Generator<int,RecurringInvoice> autoPaging(array $query = [], array $options = [])
 */
final class RecurringInvoices extends AbstractResource
{
    protected function path(): string
    {
        return 'recurring-invoices';
    }

    protected function modelClass(): string
    {
        return RecurringInvoice::class;
    }
}
