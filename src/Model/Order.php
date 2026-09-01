<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * An order - "Auftrag" (`/api/v1/orders`, feature `orders`).
 *
 * **What it is.** The work container between a quote and its invoices. Until
 * 2026-08-28 a "Projekt" carried tasks, time, costs, a rate, a customer and an
 * invoice button all at once; the order took that role over, and the project
 * shrank to a reporting bracket over any number of orders. A won quote becomes
 * an order first, and the order is what gets invoiced - which is why
 * {@see \BeckonBilling\ApiClient\Resource\Quotes::convert()} is gone.
 *
 * **Its own number range**, `A-{YYYY}-{NNNN}`, printed as `$public_index`.
 *
 * ---------------------------------------------------------------------------
 * TWO THINGS THIS API DELIBERATELY DOES NOT GIVE YOU, and they are not
 * oversights:
 *
 * 1. **No internal rate, no profitability.** The portal's own payload carries
 *    `internal_hourly_rate`, `resolved_internal_rate`, `resolved_hourly_rate`
 *    and a `financials` block with the margin. `/api/v1` is the surface for
 *    OTHER systems and leaves all four out. `$hourly_rate` - what the CUSTOMER
 *    is billed - is here; what the work costs you is not. Sending any of the
 *    four on a write is accepted and ignored rather than refused, so a
 *    read-modify-write of a payload you got from the portal does not fail.
 * 2. **No sub-routes.** The portal can draft an invoice or a recurring
 *    template from an order; neither is reachable here. Both create documents
 *    and need their own checks, so they are postponed, not forgotten. Every
 *    `/orders/{id}/<anything>` answers 404.
 *
 * @property-read string      $id
 * @property-read string|null $organisation_id  Uuid of the owning organisation. Matters with a USER token,
 *                                              which may span several organisations.
 * @property-read string|null $created_by       Uuid of the user who created this record; null = system-generated.
 * @property-read string|null $created_by_name  Display name of the creator; '' when created_by is null.
 * @property-read int|null    $sequence         Running number within the year.
 * @property-read string|null $public_index     The printed order number, e.g. "A-2026-0007".
 * @property-read string|null $label            Short title. REQUIRED on create (422 `label_required`).
 * @property-read string|null $description
 * @property-read string|null $status           "on_hold" | "in_progress" | "abnahme" | "completed" | "archived".
 *                                              READ-ONLY here: it is not among the writable keys, so a status
 *                                              move is a portal action, not a PUT.
 * @property-read string|null $color            Board colour, free-form.
 * @property-read string|null $notes
 * @property-read string|null $customer_id      Uuid. REQUIRED on create (422 `order_customer_required`) - an
 *                                              order without a customer cannot be invoiced, so the portal
 *                                              refuses one. Measured against the live API, not assumed.
 * @property-read string|null $customer_label   Display name of that customer; '' when there is none.
 * @property-read string|null $customer_company_name
 * @property-read string|null $project_id       Uuid of the reporting bracket, or null.
 * @property-read string|null $project_label
 * @property-read string|null $quote_id         Uuid of the quote this order came from, or null. At most ONE
 *                                              order per quote - the database enforces it, and a second
 *                                              conversion answers 409 `order_already_converted`.
 * @property-read array|null  $billed_quote_position_indexes Which of that quote's lines are already invoiced.
 *                                              READ-ONLY: it cannot be written back, or one could claim a
 *                                              line was never billed.
 * @property-read string|null $issue_date       ISO YYYY-MM-DD.
 * @property-read string|null $start_date       ISO YYYY-MM-DD. Derived from the work, not writable here.
 * @property-read string|null $end_date         ISO YYYY-MM-DD.
 * @property-read float|null  $hourly_rate      The CUSTOMER-billing rate pinned to this order, or null when it
 *                                              inherits (order -> customer -> organisation). Not to be confused
 *                                              with the internal rate, which this API does not expose.
 * @property-read float|null  $budget_gross
 * @property-read string|null $deposit_type     "none" | "percent" | "amount" | "goods_percent" | "service_percent".
 *                                              The order's OWN down payment, independent of the quote's.
 * @property-read float|null  $deposit_value    A percent 0-100, or a EUR amount for "amount".
 * @property-read string|null $settled_at       ISO datetime, set while a final invoice for this order is issued
 *                                              and not cancelled. READ-ONLY, and deliberately not settable: a
 *                                              stamp one can set is a stamp that can lie.
 */
final class Order extends Entity
{
    /** Everything but `archived` - the states an order is still worked in. */
    public function isOpen(): bool
    {
        return in_array($this->attributes['status'] ?? null, ['on_hold', 'in_progress', 'abnahme', 'completed'], true);
    }

    /**
     * Is a final invoice out for this order?
     *
     * Reads `settled_at`, which the portal keeps in step with the invoice: it
     * is cleared again if that invoice is cancelled. A missing key answers
     * false rather than throwing, so an older payload stays usable.
     */
    public function isSettled(): bool
    {
        return ($this->attributes['settled_at'] ?? null) !== null && $this->attributes['settled_at'] !== '';
    }
}
