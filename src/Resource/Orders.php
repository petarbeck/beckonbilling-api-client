<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\Order;

/**
 * Orders - "Auftraege", `/api/v1/orders` (feature: `orders`).
 *
 * Read, create, update, delete, like the entities that came before. What it
 * does NOT have is deliberate and documented on {@see Order}: no internal rate
 * and no profitability figures, and no sub-routes - the portal's
 * invoice-from-order and recurring-from-order actions create documents and
 * are postponed, so `/orders/{id}/<anything>` answers 404.
 *
 * `create()` requires `label` AND `customer_id` (422 `label_required` /
 * `order_customer_required`).
 *
 * `list()` filters: `status`, `customer_id` (a uuid of YOUR organisation - a
 * foreign one is refused rather than quietly matching nothing), and `q` for a
 * free-text search, plus the usual `limit`/`offset`.
 *
 * Deleting is refused with 409 `order_has_issued_invoices` once an issued
 * invoice hangs off the order.
 *
 * @method Order get(string $id, array $options = [])
 * @method Order create(array $data, array $options = [])
 * @method Order update(string $id, array $data, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<Order> list(array $query = [], array $options = [])
 * @method \Generator<int,Order> autoPaging(array $query = [], array $options = [])
 */
final class Orders extends AbstractResource
{
    protected function path(): string
    {
        return 'orders';
    }

    protected function modelClass(): string
    {
        return Order::class;
    }
}
