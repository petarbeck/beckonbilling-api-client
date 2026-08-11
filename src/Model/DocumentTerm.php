<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A terms preset - "Bedingungen" (`/api/v1/document-terms`).
 *
 * Read-only on this API, and the way to discover which ids exist for the
 * `document_term_id` write-only input on a quote / invoice / recurring invoice.
 * Sending that id LOADS this preset's `text` and `days` onto the document,
 * which then owns its own snapshot - editing or deleting the preset afterwards
 * never changes a document that already exists.
 *
 * @property-read string      $id
 * @property-read string|null $kind       "quote" | "outbound_invoice". Which document type this preset belongs
 *                                        to; a preset of the wrong kind is refused with 404 `term_not_found`.
 * @property-read string|null $label      What this preset is called in the portal.
 * @property-read int|null    $days       Validity (quote) or payment term (invoice) in days. 0 is a real value
 *                                        meaning "due immediately". Applying the preset moves the document's
 *                                        `valid_until` / `due_date` by this many days.
 * @property-read string|null $text       The wording snapshotted onto the document as `terms_text`.
 * @property-read bool|null   $is_default Whether a new document of this kind starts from this preset. At most
 *                                        one per organisation and kind.
 */
final class DocumentTerm extends Entity
{
}
