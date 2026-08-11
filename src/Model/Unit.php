<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * A unit of measure from the organisation's vocabulary (`/api/v1/units`).
 *
 * Read-only on this API. It exists because the server VALIDATES a position's
 * `unit` against this list: a short form that is not in it is refused with 422
 * the vocabulary. So this is the list to pick from, not a display nicety -
 * a value outside it is adopted, which grows the vocabulary by accident.
 *
 * The reason a unit is managed vocabulary rather than free text is `plural`.
 * A document prints the unit inflected - "12 Monate", not "12 Monat" - and a
 * typed value has no plural form to inflect to.
 *
 * @property-read string      $id
 * @property-read string|null $short         The printed short form, e.g. "h", "Stk.", "Monat". THIS is what a
 *                                           position's `unit` must match (case-insensitively).
 * @property-read string|null $plural        The form printed once the quantity leaves 1, e.g. "Monate".
 *                                           Empty means the unit does not inflect ("8 h").
 * @property-read string|null $label         The long human name, e.g. "Stunde".
 * @property-read string|null $display_label What the portal's pickers show; `short` plus the label when it has one.
 * @property-read int|null    $position      Sort order within the vocabulary.
 */
final class Unit extends Entity
{
}
