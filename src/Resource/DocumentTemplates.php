<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\DocumentTemplate;

/**
 * Document templates, "Vorlagen" - `/api/v1/document-templates`. **Read-only.**
 *
 * Where the ids for the write-only `document_template_id` input come from.
 * Filter by kind with `list(['kind' => 'quote'])` or `'invoice'` - note the
 * second value is `invoice`, NOT the `outbound_invoice` the removed
 * `/document-terms` used.
 *
 * **This replaced `DocumentTerms`**, which was removed together with its
 * endpoint. A template carries everything a terms preset did (the wording, the
 * day count) plus the second terms text, the footer, the cover-mail text, the
 * default attachments and the per-kind deposit or bank details.
 *
 * Readable by a token with VIEW on `quotes` or `outbound_invoices`, and the
 * ROWS are filtered to the kinds that token may actually view - a quotes-only
 * token never sees the invoice templates, and asking for a kind it cannot view
 * answers an empty list rather than a 403 (which would leak that the other kind
 * exists).
 *
 * @method DocumentTemplate get(string $id, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<DocumentTemplate> list(array $query = [], array $options = [])
 * @method \Generator<int,DocumentTemplate> autoPaging(array $query = [], array $options = [])
 */
final class DocumentTemplates extends ReadOnlyResource
{
    protected function path(): string
    {
        return 'document-templates';
    }

    protected function modelClass(): string
    {
        return DocumentTemplate::class;
    }
}
