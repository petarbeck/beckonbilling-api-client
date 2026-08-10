<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A recurring invoice template (`/api/v1/recurring-invoices`). The portal's
 * daily agent generates invoices from it; there is no generate endpoint.
 *
 * @property-read string      $id
 * @property-read string|null $created_by      Uuid of the user who created this record; null = system-generated (e.g. a recurring run).
 * @property-read string|null $created_by_name Display name of the creator; '' when created_by is null.
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
 * @property-read string|null $last_run_at     ISO datetime. The last time the portal's agent actually ATTEMPTED to run this
 *                                              template (generate + send) - never set on a run that was skipped because the
 *                                              current period was already generated. Null before the first attempt.
 * @property-read string|null $last_run_error  The failure message of the last attempted run, '' when it succeeded (or none
 *                                              has been attempted yet). Cleared by the NEXT successful run, so a non-empty
 *                                              value always means "still needs attention" - poll this to detect a template
 *                                              the daily agent could not bill, e.g. because the organisation has no SMTP
 *                                              configured.
 */
final class RecurringInvoice extends Entity
{
}
