<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A document template - "Vorlage" (`/api/v1/document-templates`).
 *
 * Read-only on this API, and the way to discover which ids exist for the
 * `document_template_id` write-only input on a quote / invoice / recurring
 * invoice. Sending that id LOADS this template's `terms_text`,
 * `payment_terms_text` and `days` onto the document, which then owns its own
 * snapshot - editing or deleting the template afterwards never changes a
 * document that already exists.
 *
 * **Replaced `DocumentTerm`**, which was removed along with `/document-terms`.
 * A template is the bigger thing a terms preset was part of: it also carries the
 * printed footer, the cover-mail text, the attachments a new document starts
 * with, and - per kind - the quote's default down payment or the invoice's bank
 * details. The organisation's DEFAULT template is now its document setting.
 *
 * @property-read string      $id
 * @property-read string|null $kind       "quote" | "invoice". **Not "outbound_invoice"** - the kinds differ from
 *                                        the removed document terms, so a value copied from older code is
 *                                        unknown. A template of the wrong kind is refused with 404
 *                                        `document_template_not_found`.
 * @property-read string|null $label      What this template is called in the portal.
 * @property-read bool|null   $is_default Whether a new document of this kind starts from it. At most one per
 *                                        organisation and kind, and one always exists - the last template of a
 *                                        kind cannot be deleted.
 * @property-read string|null $terms_text The document's own terms, snapshotted onto it under the same name.
 * @property-read string|null $payment_terms_text The payment terms, snapshotted under the same name and printed
 *                                        AFTER terms_text.
 * @property-read int|null    $days       Validity (quote) or payment term (invoice) in days. 0 is a real value
 *                                        meaning "due immediately". Applying the template moves the document's
 *                                        valid_until / due_date by this many days.
 * @property-read string|null $footer     Printed footer for documents started from this template.
 * @property-read string|null $email_body Cover-mail text those documents are sent with.
 * @property-read array|null  $document_ids Uuids of the attachments a NEW document starts with. Carried at
 *                                        creation only - naming a template on an existing document loads its
 *                                        texts and days, never its attachments.
 * @property-read string|null $deposit_type  Down payment a new QUOTE starts with. Meaningless on an invoice.
 * @property-read float|null  $deposit_value
 * @property-read string|null $bank_mode  How bank details print on an INVOICE from this template. Meaningless
 *                                        on a quote - a quote carries no bank details any more.
 * @property-read string|null $bank_text
 * @property-read string|null $bank_account_id Uuid of the designated bank account; '' when none.
 * @property-read bool|null   $can_delete False for the last template of its kind.
 * @property-read string|null $created_at ISO datetime.
 */
final class DocumentTemplate extends Entity
{
}
