<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\ArticleCategory;

/**
 * Article categories - `/api/v1/article-categories` (feature: `articles`).
 *
 * @method ArticleCategory get(string $id, array $options = [])
 * @method ArticleCategory create(array $data, array $options = [])
 * @method ArticleCategory update(string $id, array $data, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<ArticleCategory> list(array $query = [], array $options = [])
 * @method \Generator<int,ArticleCategory> autoPaging(array $query = [], array $options = [])
 */
final class ArticleCategories extends AbstractResource
{
    protected function path(): string
    {
        return 'article-categories';
    }

    protected function modelClass(): string
    {
        return ArticleCategory::class;
    }
}
