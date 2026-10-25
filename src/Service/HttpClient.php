<?php

namespace App\Service;

use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Http\Message\ResponseInterface;

class HttpClient
{
    protected ?GuzzleHttpClient $client = null;

    /**
     * Get the Guzzle HTTP client instance.
     *
     * @return GuzzleHttpClient
     */
    public function getClient()
    {
        if ($this->client === null) {
            $this->client = new GuzzleHttpClient();
        }
        return $this->client;
    }

    /**
     * Perform a GET request to the specified URL.
     *
     * @param string $url The URL to send the GET request to.
     * @return \Psr\Http\Message\ResponseInterface The response from the GET request.
     */
    public function get($url): ResponseInterface
    {
        return $this->getClient()->get($url);
    }
}