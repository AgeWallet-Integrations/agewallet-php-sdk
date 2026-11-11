<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Mocks;

use AgeWallet\Sdk\Client;

/**
 * Client with its HTTP, cookie and exit seams replaced.
 *
 * sendRequest() returns $responses in order (HTTP 500 once they run out) and records each call in $requests;
 * setCookie() records each call in $cookies instead of sending a header; terminate() counts calls instead of exiting.
 */
class RecordingClient extends Client
{
    /** @var array<int, array{status: int, body: string}> */
    public array $responses = [];

    /** @var array<int, array{url: string, options: array}> */
    public array $requests = [];

    /** @var array<int, array{name: string, value: string, expires: int, path: string, domain: string, secure: bool, httponly: bool}> */
    public array $cookies = [];

    public int $terminations = 0;

    protected function sendRequest(string $url, array $options): array
    {
        $this->requests[] = ['url' => $url, 'options' => $options];
        return array_shift($this->responses) ?? ['status' => 500, 'body' => ''];
    }

    protected function setCookie(string $name, string $value, int $expires, string $path, string $domain, bool $secure, bool $httponly): bool
    {
        $this->cookies[] = compact('name', 'value', 'expires', 'path', 'domain', 'secure', 'httponly');
        return true;
    }

    protected function terminate(): void
    {
        $this->terminations++;
    }
}
