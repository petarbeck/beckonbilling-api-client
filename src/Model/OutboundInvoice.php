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
 * @property-read float|null  $remaining_amount
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
}
