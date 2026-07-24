<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\Customer;

/**
 * Customers - `/api/v1/customers` (feature: `customers`).
 *
 * @method Customer get(string $id, array $options = [])
 * @method Customer create(array $data, array $options = [])
 * @method Customer update(string $id, array $data, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<Customer> list(array $query = [], array $options = [])
 * @method \Generator<int,Customer> autoPaging(array $query = [], array $options = [])
 */
final class Customers extends AbstractResource
{
    protected function path(): string
    {
        return 'customers';
    }

    protected function modelClass(): string
    {
        return Customer::class;
    }
}
