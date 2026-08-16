<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * One entry of the system unit catalogue (`/api/v1/units`).
 *
 * A FIXED list of 31 units, identical for every organisation and read-only.
 * It is no longer a per-organisation vocabulary: nothing you send adds to it,
 * and there is nothing to rename or retire. Reading it needs no permission -
 * it says nothing about the organisation.
 *
 * An article and a position do NOT take the same value:
 *
 *  - an ARTICLE's `unit` is the `key`, matched exactly and lowercase, and an
 *    unknown one answers 422 `unit_unknown`. A printed short form is not a key,
 *    so "Stk.", "h" and "pc." are all refused;
 *  - a POSITION states its unit in `unit_key` and stores the printed wording
 *    in `unit`. The key is resolved IN THE ISSUING ORGANISATION'S LANGUAGE and
 *    snapshotted as text, so `piece` reads back as "piece"/"pieces" for an
 *    English organisation and "Stück"/"" for a German one. `unit` alone still
 *    works and is never refused: a key in it is resolved the same way, anything
 *    else is stored verbatim.
 *
 * Prefer `unit_key` on a position. The printed wordings move - on 2026-08-16
 * "Std." became "Stunde" and "pc." became "piece" - and the keys do not. When
 * both are sent and they name the SAME unit, the stored wording is kept, so
 * echoing a document's own positions never rewrites an issued invoice.
 *
 * The plural is why the printed forms are managed at all: a document inflects
 * the unit once the quantity leaves 1 ("12 Monate", not "12 Monat"). An empty
 * plural means the unit does not inflect ("8 h").
 *
 * @property-read string      $id     Same value as `key`; present so the row has the `id` every entity here has.
 * @property-read string|null $key    The catalogue key, lowercase, e.g. "piece", "hour", "month".
 * @property-read array|null  $de     German forms: `['short' => 'Stück', 'label' => 'Stück', 'plural' => '']`.
 * @property-read array|null  $en     English forms: `['short' => 'piece', 'label' => 'Piece', 'plural' => 'pieces']`.
 */
final class Unit extends Entity
{
}
