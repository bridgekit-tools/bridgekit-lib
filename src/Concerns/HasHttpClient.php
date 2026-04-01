<?php

declare(strict_types=1);

namespace BridgeKit\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use BridgeKit\Exceptions\ProviderException;

trait HasHttpClient
{
    protected ?PendingRequest $httpClient = null;

    protected function http(): PendingRequest
    {
        if ($this->httpClient === null) {
            $this->httpClient = Http::acceptJson()
                ->contentType('application/json')
                ->throw(function (Response $response) {
                    throw new ProviderException(
                        message: "HTTP {$response->status()}: {$response->body()}",
                        provider: $this->getProviderName(),
                        code: $response->status(),
                    );
                });
        }

        return $this->httpClient;
    }

    protected function authenticatedHttp(): PendingRequest
    {
        $token = $this->getAccessToken();

        return $this->http()->withToken($token);
    }

    abstract protected function getProviderName(): string;

    abstract protected function getAccessToken(): string;
}
