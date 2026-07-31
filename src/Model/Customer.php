<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A customer (`/api/v1/customers`).
 *
 * @property-read string      $id
 * @property-read string|null $label
 * @property-read string|null $customer_type   "business" | "consumer"
 * @property-read string|null $company_name
 * @property-read string|null $attention
 * @property-read string|null $salutation
 * @property-read string|null $first_name
 * @property-read string|null $last_name
 * @property-read string|null $customer_info    Composed address block printed on documents.
 * @property-read array|null  $recipient        Structured recipient object.
 * @property-read string|null $billing_email_address
 * @property-read string|null $billing_email_name
 * @property-read string|null $contact_name
 * @property-read string|null $contact_email
 * @property-read string|null $contact_phone    E.164, e.g. "+436601791301".
 * @property-read bool|null   $reverse_charge
 * @property-read float|null  $balance          Signed; positive = customer holds credit.
 * @property-read int|null    $risk_level       Read-only dunning risk indicator.
 * @property-read string|null $quote_document_term_id   Bedingungen preset applied to a QUOTE when this
 *                                                      customer is assigned. A default, not a snapshot.
 * @property-read string|null $quote_terms_text         Negotiated wording overriding the preset's text.
 * @property-read int|null    $quote_valid_days         Overrides the preset's days. NULL = inherit; 0 is real.
 * @property-read string|null $invoice_document_term_id The same for outbound invoices; also supplies the
 *                                                      payment term, so due_date moves with it.
 * @property-read string|null $invoice_terms_text
 * @property-read int|null    $invoice_due_days         Overrides the preset's days. NULL = inherit; 0 is real.
 *
 * Any other field the API returns is reachable via property/array access too.
 */
final class Customer extends Entity
{
}
