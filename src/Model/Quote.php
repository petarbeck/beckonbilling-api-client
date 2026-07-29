<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A quote (`/api/v1/quotes`). Lifecycle: draft -> issued -> won | lost | converted.
 *
 * @property-read string      $id
 * @property-read string|null $public_index    Document number (opaque; e.g. "2607-1000-2").
 * @property-read int|null    $version         1..99.
 * @property-read string|null $status          "draft" | "issued" | "won" | "lost" | "converted".
 * @property-read string|null $customer_id
 * @property-read array|null  $recipient
 * @property-read array|null  $positions       Line items.
 * @property-read string|null $valid_until     ISO YYYY-MM-DD.
 * @property-read string|null $intro_text      Email cover text.
 * @property-read string|null $footer_comment  PDF footer.
 * @property-read array|null  $reference_fields
 * @property-read bool|null   $reverse_charge
 * @property-read string|null $terms_text      Snapshotted payment/validity terms (from the org's terms preset).
 * @property-read bool|null   $small_business  Issuer's Kleinunternehmer VAT exemption, snapshotted.
 * @property-read string|null $vat_exemption_note Statutory sentence to print when exempt; null otherwise.
 *                                             An exempt document shows a single total and this note
 *                                             INSTEAD of a net/VAT breakdown.
 * @property-read string|null $document_term_id Provenance only - the preset the text came from.
 * @property-read string|null $deposit_type    "none" | "percent" | "amount".
 * @property-read float|null  $deposit_value   Percent 0-100 for "percent", else a EUR amount.
 * @property-read float|null  $deposit_amount  Resolved down payment, capped to [0, gross_total].
 * @property-read float|null  $remaining_amount gross_total minus deposit_amount.
 * @property-read float|null  $net_total
 * @property-read float|null  $tax_total
 * @property-read float|null  $gross_total
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
