<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\DocumentTerm;

/**
 * Terms presets, "Bedingungen" - `/api/v1/document-terms`. **Read-only.**
 *
 * Where the ids for the write-only `document_term_id` input come from. Filter
 * by kind with `list(['kind' => 'quote'])` or `'outbound_invoice'`.
 *
 * Readable by a token with VIEW on `quotes` or `outbound_invoices`, and the
 * ROWS are filtered to the kinds that token may actually view - a quotes-only
 * token never sees the invoice presets, and asking for a kind it cannot view
 * answers an empty list rather than a 403 (which would leak that the other kind
 * exists).
 *
 * @method DocumentTerm get(string $id, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<DocumentTerm> list(array $query = [], array $options = [])
 * @method \Generator<int,DocumentTerm> autoPaging(array $query = [], array $options = [])
 */
final class DocumentTerms extends ReadOnlyResource
{
    protected function path(): string
    {
        return 'document-terms';
    }

    protected function modelClass(): string
    {
        return DocumentTerm::class;
    }
}
