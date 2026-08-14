<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient;

use BeckonBilling\ApiClient\Http\Transport;
use BeckonBilling\ApiClient\Resource\ArticleCategories;
use BeckonBilling\ApiClient\Resource\Articles;
use BeckonBilling\ApiClient\Resource\Auth;
use BeckonBilling\ApiClient\Resource\Customers;
use BeckonBilling\ApiClient\Resource\DocumentTemplates;
use BeckonBilling\ApiClient\Resource\OutboundInvoices;
use BeckonBilling\ApiClient\Resource\Quotes;
use BeckonBilling\ApiClient\Resource\RecurringInvoices;
use BeckonBilling\ApiClient\Resource\Units;

/**
 * Entry point for the Beckon Billing REST API (v1).
 *
 * ```php
 * $client = new \BeckonBilling\ApiClient\Client([
 *     'token'        => 'bbp_...',
 *     'base_uri'     => 'https://portal.beckonbilling.com',
 *     'organisation' => '‹organisation-uuid›', // optional default scope
 * ]);
 *
 * foreach ($client->customers->autoPaging() as $customer) {
 *     echo $customer->display_name, "\n";
 * }
 * ```
 *
 * @property-read Customers          $customers
 * @property-read ArticleCategories  $articleCategories
 * @property-read Articles           $articles
 * @property-read Units              $units             Read-only.
 * @property-read DocumentTemplates  $documentTemplates Read-only.
 * @property-read Quotes             $quotes
 * @property-read OutboundInvoices   $outboundInvoices
 * @property-read RecurringInvoices  $recurringInvoices
 * @property-read Auth               $auth
 */
final class Client
{
    public readonly Customers $customers;
    public readonly ArticleCategories $articleCategories;
    public readonly Articles $articles;
    public readonly Units $units;
    public readonly DocumentTemplates $documentTemplates;
    public readonly Quotes $quotes;
    public readonly OutboundInvoices $outboundInvoices;
    public readonly RecurringInvoices $recurringInvoices;
    public readonly Auth $auth;

    private readonly Configuration $configuration;
    private readonly Transport $transport;

    /**
     * @param array<string,mixed>|Configuration $config See {@see Configuration}.
     */
    public function __construct(array|Configuration $config)
    {
        $this->configuration = $config instanceof Configuration ? $config : new Configuration($config);
        $this->transport = new Transport($this->configuration);

        $this->customers = new Customers($this->transport);
        $this->articleCategories = new ArticleCategories($this->transport);
        $this->articles = new Articles($this->transport);
        $this->units = new Units($this->transport);
        $this->documentTemplates = new DocumentTemplates($this->transport);
        $this->quotes = new Quotes($this->transport);
        $this->outboundInvoices = new OutboundInvoices($this->transport);
        $this->recurringInvoices = new RecurringInvoices($this->transport);
        $this->auth = new Auth($this->transport);
    }

    public function configuration(): Configuration
    {
        return $this->configuration;
    }
}
