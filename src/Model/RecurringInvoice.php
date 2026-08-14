<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A recurring invoice template (`/api/v1/recurring-invoices`). The portal's
 * automation agent generates invoices from it - once a day at 08:00 in the
 * ORGANISATION's own timezone; there is no generate endpoint.
 *
 * @property-read string      $id
 * @property-read string|null $created_by      Uuid of the user who created this record; null = system-generated (e.g. a recurring run).
 * @property-read string|null $created_by_name Display name of the creator; '' when created_by is null.
 * @property-read string|null $label
 * @property-read string|null $customer_id
 * @property-read string|null $customer_label  The LIVE customer's display name, resolved server-side.
 * @property-read string|null $project_id
 * @property-read string|null $partner_id       Commission partner propagated onto generated invoices.
 * @property-read bool|null   $active
 * @property-read string|null $interval         "daily" | "weekly" | "monthly" | "yearly".
 * @property-read string|null $first_run_date   ISO YYYY-MM-DD. An ANCHOR, not a one-off event: it fixes
 *                                              the day every future run lands on.
 * @property-read string|null $next_run_at      Derived, read-only.
 * @property-read int|null    $due_days         Payment term handed to each generated invoice.
 * @property-read string|null $service_period_mode  current|previous|ahead|behind|none.
 * @property-read string|null $payment_mode     "direct_debit" | "all" | "individual". Never empty - an
 *                                              unrecognised value normalises to "all".
 * @property-read array|null  $payment_methods
 * @property-read string|null $document_send_mode "" | "link" | "attach". A template MAY stay empty, unlike a
 *                                                quote or an invoice: it then leaves delivery to the customer,
 *                                                else the organisation.
 * @property-read string|null $payment_method   LEGACY. "BANK_TRANSFER" | "DIRECT_DEBIT".
 * @property-read string|null $tax_label
 * @property-read bool|null   $reverse_charge
 * @property-read string|null $terms_text      Terms copied onto every generated invoice.
 * @property-read string|null $payment_terms_text The SECOND terms text, copied onto every generated invoice too
 *                                             and printed AFTER terms_text.
 * @property-read string|null $email_text      Cover-mail intro for every generated invoice.
 * @property-read string|null $email_body
 * @property-read string|null $pdf_footer
 * @property-read string|null $pdf_note
 * @property-read array|null  $positions
 * @property-read array|null  $reference_fields
 * @property-read array|null  $document_ids    Read-only here: INTERNAL INTEGER ids, not UUIDs (the one
 *                                             place this API exposes them). Do not build on them.
 * @property-read string|null $last_generated_period  The period key the last successful run consumed.
 * @property-read string|null $last_run_at     ISO datetime. The last time the portal's agent actually ATTEMPTED to run this
 *                                              template (generate + send) - never set on a run that was skipped because the
 *                                              current period was already generated. Null before the first attempt.
 * @property-read string|null $last_run_error  The message of the last attempted run, '' when it succeeded (or none
 *                                              has been attempted yet). Cleared by the NEXT successful run, so a non-empty
 *                                              value always means "still needs attention" - poll this to detect a template
 *                                              the agent could not bill, e.g. because the organisation has no SMTP
 *                                              configured. Read `last_run_severity` before calling it a failure.
 * @property-read string|null $last_run_severity  '' when there is no message, else "error" (nothing was billed) or
 *                                                "warning" (the invoice WAS generated; something after it needs a look).
 */
final class RecurringInvoice extends Entity
{
}
