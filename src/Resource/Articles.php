<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Collection;
use BeckonBilling\ApiClient\Model\Article;
use BeckonBilling\ApiClient\Model\ArticleVariant;

/**
 * Articles - `/api/v1/articles` (feature: `articles`).
 *
 * @method Article get(string $id, array $options = [])
 * @method Article create(array $data, array $options = [])
 * @method Article update(string $id, array $data, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<Article> list(array $query = [], array $options = [])
 * @method \Generator<int,Article> autoPaging(array $query = [], array $options = [])
 */
final class Articles extends AbstractResource
{
    protected function path(): string
    {
        return 'articles';
    }

    protected function modelClass(): string
    {
        return Article::class;
    }

    /**
     * One article's variants - named override profiles on top of it.
     *
     * A sub-collection rather than a resource of its own, because a variant has
     * no meaning away from its article: every field it does not set is inherited
     * from the article, and it is the ARTICLE that decides which organisation
     * the variant belongs to.
     *
     * @param array<string,mixed> $query   e.g. ['limit' => 50, 'offset' => 0]
     * @param array<string,mixed> $options
     * @return Collection<ArticleVariant>
     */
    public function variants(string $articleId, array $query = [], array $options = []): Collection
    {
        $options['query'] = $query;
        $response = $this->transport->request('GET', $this->variantPath($articleId), $options);

        $rows = is_array($response['data'] ?? null) ? $response['data'] : [];
        $data = [];
        foreach ($rows as $row) {
            $data[] = new ArticleVariant(is_array($row) ? $row : []);
        }

        return new Collection(
            $data,
            (int) ($response['total'] ?? count($data)),
            (int) ($response['limit'] ?? count($data)),
            (int) ($response['offset'] ?? 0),
        );
    }

    /**
     * Create a variant of this article. Send ONLY the fields it should
     * override - anything omitted stays inherited. `label` is required and is
     * never inherited.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    public function createVariant(string $articleId, array $data, array $options = []): ArticleVariant
    {
        $options['json'] = $data;
        $response = $this->transport->request('POST', $this->variantPath($articleId), $options);

        return new ArticleVariant($response);
    }

    /**
     * Update a variant. Pass `null` for a field to CLEAR the override and go
     * back to inheriting the article's value - which is a different thing from
     * omitting it (omitted = leave as it is).
     *
     * There is deliberately no delete: a variant a document already used is
     * retired in the portal, and the document keeps the values and the label it
     * snapshotted either way.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    public function updateVariant(string $articleId, string $variantId, array $data, array $options = []): ArticleVariant
    {
        $options['json'] = $data;
        $response = $this->transport->request(
            'PUT',
            $this->variantPath($articleId) . '/' . rawurlencode($variantId),
            $options
        );

        return new ArticleVariant($response);
    }

    private function variantPath(string $articleId): string
    {
        return $this->itemPath($articleId) . '/variants';
    }
}
