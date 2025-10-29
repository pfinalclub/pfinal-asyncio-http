<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use Psr\Http\Client\ClientInterface as Psr18ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 客户端实现
 */
class Psr18Client implements Psr18ClientInterface
{
    private Client $client;

    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->send($request);
    }
}

