<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A recurring invoice template (`/api/v1/recurring-invoices`). The portal's
 * daily agent generates invoices from it; there is no generate endpoint.
 *
 * @property-read string      $id
 * @property-read string|null $label
 * @property-read string|null $customer_id
 * @property-read string|null $partner_id       Commission partner propagated onto generated invoices.
 * @property-read bool|null   $active
 * @property-read string|null $interval         "daily" | "weekly" | "monthly" | "yearly".
 * @property-read string|null $first_run_date   ISO YYYY-MM-DD.
 * @property-read string|null $next_run_at      Derived, read-only.
 * @property-read int|null    $due_days
 * @property-read string|null $payment_method   "BANK_TRANSFER" | "DIRECT_DEBIT".
 * @property-read string|null $tax_label
 * @property-read string|null $terms_text      Payment terms copied onto every generated invoice.
 * @property-read array|null  $positions
 */
final class RecurringInvoice extends Entity
{
}
