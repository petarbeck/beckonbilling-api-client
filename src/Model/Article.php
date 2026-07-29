<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A catalog article (`/api/v1/articles`).
 *
 * @property-read string      $id
 * @property-read string|null $title
 * @property-read string|null $description
 * @property-read float|null  $price             Net unit price.
 * @property-read float|null  $tax_percent
 * @property-read string|null $unit            Default short unit for lines created from this article.    Tax percentage, e.g. 20.
 * @property-read string|null $article_group_id  Category UUID.
 * @property-read string|null $fibu_code         Revenue/ledger account for the export.
 * @property-read bool|null   $commission_eligible
 */
final class Article extends Entity
{
}
