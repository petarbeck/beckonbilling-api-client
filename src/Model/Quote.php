<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A quote (`/api/v1/quotes`). Lifecycle: draft -> issued -> won | lost | converted.
 *
 * @property-read string      $id
 * @property-read string|null $created_by      Uuid of the user who created this record; null = system-generated (e.g. a recurring run).
 * @property-read string|null $created_by_name Display name of the creator; '' when created_by is null.
 * @property-read string|null $public_index    Document number (opaque; e.g. "2607-1000-2").
 * @property-read int|null    $version         1..99.
 * @property-read string|null $status          "draft" | "issued" | "won" | "lost" | "converted".
 * @property-read string|null $customer_id
 * @property-read array|null  $recipient
 * @property-read array|null  $positions       Line items.
 * @property-read string|null $valid_until     ISO YYYY-MM-DD.
 * @property-read string|null $email_text      Email cover text. Also emitted as `intro_text` (legacy alias).
 * @property-read string|null $intro_text      Legacy alias of email_text.
 * @property-read string|null $pdf_footer      Printed footer. Was `footer_comment`, which is no longer emitted.
 * @property-read string|null $project_id
 * @property-read string|null $partner_id
 * @property-read array|null  $document_ids    Attached document UUIDs.
 * @property-read string|null $document_send_mode "link" | "attach".
 * @property-read string|null $language        "" | "DE" | "EN". Stamped at creation from the recipient's
 *                                             country (DE for DE/AT/CH, else EN; the organisation's own
 *                                             language only when the recipient has no country). An explicit
 *                                             value wins and is snapshotted. "" on an existing quote means it
 *                                             predates this field.
 * @property-read array|null  $reference_fields
 * @property-read bool|null   $reverse_charge
 * @property-read string|null $terms_text      Snapshotted terms ("Angebotsbedingungen"), from the selected or
 *                                             default document template.
 * @property-read string|null $payment_terms_text The SECOND terms text, printed AFTER terms_text. On a quote it
 *                                             holds the down-payment terms, and it is printed only when the
 *                                             quote carries a real down payment (deposit_type set AND
 *                                             deposit_value > 0). Converting a quote does NOT carry it onto the
 *                                             invoice - it means deposit terms here, payment terms there.
 * @property-read bool|null   $small_business  Issuer's Kleinunternehmer VAT exemption, snapshotted.
 * @property-read string|null $vat_exemption_note Statutory sentence to print when exempt; null otherwise.
 *                                             An exempt document shows a single total and this note
 *                                             INSTEAD of a net/VAT breakdown.
 * @property-read string|null $deposit_type    "none" | "percent" | "amount" | "goods_percent" | "service_percent".
 *                                             The last two apply the percentage to only the goods or only the
 *                                             service lines, decided per line by its supply_type.
 * @property-read float|null  $deposit_value   A percent 0-100 for "percent", "goods_percent" and
 *                                             "service_percent"; a EUR amount for "amount".
 * @property-read float|null  $deposit_amount  Resolved down payment, capped to [0, basis] - the basis being the
 *                                             ONE-TIME gross total, or only its goods/service part for the two
 *                                             supply-specific types. Never capped to gross_total, which carries
 *                                             recurring lines a down payment never covers.
 * @property-read float|null  $remaining_amount The one-time gross total minus deposit_amount.
 * @property-read float|null  $net_total
 * @property-read float|null  $tax_total
 * @property-read float|null  $gross_total
 * @property-read string|null $converted_outbound_invoice_id  Set once the quote was converted. SINGLE-valued, so
 *                                             it cannot describe a quote billed in stages - read
 *                                             OutboundInvoice::$quote_id to find every invoice of a quote.
 * @property-read string|null $converted_recurring_invoice_id Set when its recurring half produced a template.
 * @property-read string|null $accepted_at     ISO datetime; set when the customer accepted it online.
 * @property-read bool|null   $has_signature   Whether that acceptance carries a drawn signature.
 * @property-read string|null $rejected_at     ISO datetime; set when the customer declined it online.
 * @property-read string|null $rejection_comment
 * @property-read int|null    $view_count
 * @property-read int|null    $download_count
 */
final class Quote extends Entity
{
    public function isDraft(): bool
    {
        return ($this->attributes['status'] ?? null) === 'draft';
    }
}
