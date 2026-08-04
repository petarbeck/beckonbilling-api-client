<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * An outbound invoice / Ausgangsrechnung (`/api/v1/outbound-invoices`).
 *
 * @property-read string      $id
 * @property-read string|null $public_index      Document number (opaque).
 * @property-read string|null $status            "draft" | "issued".
 * @property-read string|null $customer_id
 * @property-read array|null  $recipient
 * @property-read array|null  $sender            Organisation data, frozen at issue.
 * @property-read array|null  $positions
 * @property-read string|null $issue_date        ISO YYYY-MM-DD.
 * @property-read string|null $due_date          ISO YYYY-MM-DD.
 * @property-read bool|null   $paid              Derived from payment records.
 * @property-read float|null  $paid_amount
 * @property-read string|null $paid_at           ISO datetime; null until fully paid.
 * @property-read float|null  $remaining_amount  Still outstanding. 0 once paid AND 0 once cancelled.
 * @property-read string|null $settlement_status "draft"|"open"|"partially_paid"|"paid"|"cancelled".
 *                                             Prefer this over $paid to decide whether money is owed.
 * @property-read bool|null   $cancelled         Reversed by a cancellation credit note.
 * @property-read string|null $cancels_outbound_invoice_id      On a credit note: the invoice it reverses.
 * @property-read string|null $cancels_outbound_invoice_number  That invoice's document number.
 * @property-read string|null $cancelled_by_outbound_invoice_id     On a cancelled invoice: the credit note.
 * @property-read string|null $cancelled_by_outbound_invoice_number That credit note's document number.
 * @property-read bool|null   $credit_note       Derived: true when positions net negative.
 * @property-read bool|null   $reverse_charge
 * @property-read string|null $terms_text      Snapshotted payment terms (from the org's terms preset).
 * @property-read bool|null   $small_business  Issuer's Kleinunternehmer VAT exemption, snapshotted.
 * @property-read string|null $vat_exemption_note Statutory sentence to print when exempt; null otherwise.
 *                                             An exempt document shows a single total and this note
 *                                             INSTEAD of a net/VAT breakdown.
 * @property-read string|null $sent_at           ISO datetime.
 * @property-read float|null  $net_total
 * @property-read float|null  $tax_total
 * @property-read float|null  $gross_total
 * @property-read string|null $partner_id
 * @property-read string|null $send_error        Present if issue/send mailing failed.
 */
final class OutboundInvoice extends Entity
{
    public function isPaid(): bool
    {
        return (bool) ($this->attributes['paid'] ?? false);
    }

    public function isDraft(): bool
    {
        return ($this->attributes['status'] ?? null) === 'draft';
    }

    public function isCreditNote(): bool
    {
        return (bool) ($this->attributes['credit_note'] ?? false);
    }

    /**
     * Reversed by a cancellation credit note. It keeps its number and its
     * "issued" status - it stays part of the record - but owes nothing.
     */
    public function isCancelled(): bool
    {
        return (bool) ($this->attributes['cancelled'] ?? false);
    }

    /**
     * "draft" | "open" | "partially_paid" | "paid" | "cancelled".
     */
    public function settlementStatus(): ?string
    {
        $status = $this->attributes['settlement_status'] ?? null;

        return null === $status ? null : (string) $status;
    }

    /**
     * Whether money is still expected on this document - the question `paid`
     * alone answers wrongly.
     *
     * A CANCELLED invoice is not outstanding: it was reversed, and whatever is
     * still owed in either direction sits on the credit note that reversed it.
     * Reading `paid === false` as "outstanding" reports a storno as an unpaid
     * bill, which is the mistake this method exists to prevent.
     *
     * On a credit note this is true while a refund is still owed to the
     * customer - money going out rather than coming in.
     */
    public function isOutstanding(): bool
    {
        if ($this->isDraft() || $this->isCancelled() || $this->isPaid()) {
            return false;
        }

        // Same cent tolerance the server settles with.
        return (float) ($this->attributes['remaining_amount'] ?? 0.0) >= 0.005;
    }
}
