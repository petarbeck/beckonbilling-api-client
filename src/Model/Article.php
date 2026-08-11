<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A catalog article (`/api/v1/articles`).
 *
 * @property-read string      $id
 * @property-read string|null $created_by      Uuid of the user who created this record; null = system-generated (e.g. a recurring run).
 * @property-read string|null $created_by_name Display name of the creator; '' when created_by is null.
 * @property-read string|null $title
 * @property-read string|null $description
 * @property-read float|null  $price             Net unit price.
 * @property-read float|null  $tax_percent       Tax percentage, e.g. 20.
 * @property-read string|null $unit              Default short unit for lines created from this article, e.g. "h", "Stk.".
 *                                               Must be one of the organisation's units - see `GET /units`.
 * @property-read string|null $article_group_id  Category UUID.
 * @property-read string|null $group_label       That category's name, resolved server-side; '' when uncategorised.
 * @property-read string|null $fibu_code         Revenue/ledger account for the export.
 * @property-read bool|null   $commission_eligible
 * @property-read string|null $supply_type         service|goods - decides which sentence a cross-border
 *                                                 document prints for lines created from this article.
 * @property-read string|null $billing_mode        one_time|recurring - the default modality for a
 *                                                 quote line created from this article.
 * @property-read string|null $recurring_interval  monthly|quarterly|yearly; only with billing_mode=recurring.
 * @property-read int|null    $variant_count       How many live variants this article has; 0 = used as it stands.
 */
final class Article extends Entity
{
}
