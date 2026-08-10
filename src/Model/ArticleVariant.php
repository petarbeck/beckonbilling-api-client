<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A variant of a catalog article (`/api/v1/articles/{id}/variants`).
 *
 * A variant is a named set of OVERRIDES on its article - "Premium", "Klein",
 * "Kleinunternehmer". Every override property below is genuinely nullable and
 * the distinction matters: `null` means the article's own value is inherited,
 * and a value - INCLUDING 0 - is a real override. A variant that overrides
 * nothing produces exactly what the plain article produces.
 *
 * So never fold a null into a default when reading one of these:
 * `$variant->price ?? $article->price` is right, `$variant->price ?: 20.0` is
 * the mistake that turns a 0 % rate into 20 %.
 *
 * @property-read string      $id
 * @property-read string|null $article_id       The article this variant belongs to. Immutable.
 * @property-read string|null $created_by       Uuid of the user who created this record; null = system-generated.
 * @property-read string|null $created_by_name  Display name of the creator; '' when created_by is null.
 * @property-read string|null $label            What this variant is called. Required, never inherited.
 * @property-read int|null    $sort_order
 * @property-read string|null $title
 * @property-read string|null $description
 * @property-read float|null  $price            Net unit price; null = inherit, 0 = a real price.
 * @property-read float|null  $tax_percent      Percentage, e.g. 20; null = inherit, 0 = a real rate.
 * @property-read string|null $unit
 * @property-read string|null $fibu_code
 * @property-read string|null $supply_type         service|goods
 * @property-read string|null $billing_mode        one_time|recurring
 * @property-read string|null $recurring_interval  monthly|quarterly|yearly
 * @property-read bool|null   $commission_eligible
 */
final class ArticleVariant extends Entity
{
}
