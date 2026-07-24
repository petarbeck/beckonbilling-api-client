<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\Article;

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
}
