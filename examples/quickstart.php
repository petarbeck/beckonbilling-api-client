<?php

/**
 * Runnable quickstart.
 *
 *   composer require beckonbilling/api-client guzzlehttp/guzzle
 *   BECKONBILLING_TOKEN=bbp_... BECKONBILLING_HOST=https://portal.beckonbilling.com \
 *   BECKONBILLING_ORG=<org-uuid> php examples/quickstart.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BeckonBilling\ApiClient\Client;
use BeckonBilling\ApiClient\Exception\ApiException;

$client = new Client([
    'token' => getenv('BECKONBILLING_TOKEN') ?: 'bbp_...',
    'base_uri' => getenv('BECKONBILLING_HOST') ?: 'https://portal.beckonbilling.com',
    'organisation' => getenv('BECKONBILLING_ORG') ?: null,
]);

try {
    // 1. List a page of customers.
    $page = $client->customers->list(['limit' => 5]);
    printf("Customers: %d of %d total\n", count($page), $page->total);
    foreach ($page as $customer) {
        printf("  - %s (%s)\n", $customer->label ?? '(no label)', $customer->id());
    }

    // 2. Iterate every open, issued invoice lazily.
    $open = 0.0;
    foreach ($client->outboundInvoices->autoPaging(['status' => 'issued', 'paid' => 0]) as $invoice) {
        $open += (float) ($invoice->remaining_amount ?? 0);
    }
    printf("Total open amount across issued invoices: %.2f\n", $open);
} catch (ApiException $e) {
    fwrite(STDERR, sprintf(
        "API error %d (%s): %s\n",
        $e->getStatusCode(),
        $e->getErrorKey() ?? 'unknown',
        $e->getMessage(),
    ));
    exit(1);
}
