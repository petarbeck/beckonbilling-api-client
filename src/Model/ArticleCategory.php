<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * An article category (`/api/v1/article-categories`), gated by the `articles`
 * feature.
 *
 * @property-read string      $id
 * @property-read string|null $organisation_id  Uuid of the owning organisation. Matters with a USER token,
 *                                              which may span several organisations.
 * @property-read string|null $label
 * @property-read string|null $fibu_code   Default revenue account for its articles.
 */
final class ArticleCategory extends Entity
{
}
