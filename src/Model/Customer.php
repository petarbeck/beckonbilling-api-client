<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A customer (`/api/v1/customers`).
 *
 * @property-read string      $id
 * @property-read string|null $created_by      Uuid of the user who created this record; null = system-generated (e.g. a recurring run).
 * @property-read string|null $created_by_name Display name of the creator; '' when created_by is null.
 * @property-read string|null $display_name    The name to show: company_name for a business, salutation + person_name for a private person.
 * @property-read string|null $customer_type   "business" | "consumer"
 * @property-read string|null $company_name
 * @property-read string|null $attention
 * @property-read string|null $salutation
 * @property-read string|null $person_name
 * @property-read string|null $address
 * @property-read string|null $zip
 * @property-read string|null $city
 * @property-read string|null $country          ISO-2 country code.
 * @property-read string|null $vat_id
 * @property-read string|null $email
 * @property-read string|null $additional       Free-form extra address lines.
 * @property-read string|null $homepage         Stored without the scheme, e.g. "www.example.at".
 * @property-read string|null $imprint_url      Informational only; never printed on a document.
 * @property-read string|null $privacy_url      Informational only.
 * @property-read string|null $customer_info    Composed address block printed on documents.
 * @property-read string|null $recipient_email  Where this customer's documents are sent.
 * @property-read string|null $recipient_email_name
 * @property-read string|null $cc_email
 * @property-read string|null $cc_email_name
 * @property-read array|null  $contacts         Contact people: [{type, name, email, phone}]; phones are E.164.
 * @property-read string|null $bank_account_holder
 * @property-read string|null $bank_iban
 * @property-read string|null $bank_bic
 * @property-read string|null $payment_mode     "direct_debit" | "all" | "individual".
 * @property-read array|null  $payment_methods  Selection used when payment_mode is "individual".
 * @property-read string|null $payment_method   LEGACY, frozen. Read payment_mode instead.
 * @property-read string|null $document_send_mode "" (platform default) | "link" | "attach".
 * @property-read bool|null   $reverse_charge
 * @property-read string|null $partner_id       Commission partner stamped onto this customer's documents.
 * @property-read float|null  $partner_commission_percent  -1 = inherit the partner profile's rate.
 * @property-read float|null  $hourly_rate      Null = inherit the organisation rate.
 * @property-read float|null  $inherited_rate   What hourly_rate would resolve to with no override.
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
