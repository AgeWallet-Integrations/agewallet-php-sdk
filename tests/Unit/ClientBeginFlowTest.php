<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Unit;

use AgeWallet\Sdk\Client;
use AgeWallet\Sdk\Security\StandardSecurityGenerator;
use AgeWallet\Sdk\Tests\Mocks\InMemorySessionHandler;
use AgeWallet\Sdk\Tests\Mocks\MockSecurityGenerator;
use AgeWallet\Sdk\Tests\Mocks\RecordingClient;
use PHPUnit\Framework\TestCase;

class ClientBeginFlowTest extends TestCase
{
    private InMemorySessionHandler $session;
    private array $config;

    protected function setUp(): void
    {
        $this->session = new InMemorySessionHandler();
        $this->config = [
            'client_id' => 'test-id',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'http://localhost/callback',
            'hmac_secret' => 'test-hmac'
        ];
        $_GET = [];
        $_COOKIE = [];
    }

    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        return $query;
    }

    public function testAuthorizeUrlCarriesEveryOidcParameter(): void
    {
        $client = new Client($this->config, $this->session, new MockSecurityGenerator());

        $query = $this->query($client->beginFlow()['url']);

        $this->assertSame([
            'response_type' => 'code',
            'client_id' => 'test-id',
            'redirect_uri' => 'http://localhost/callback',
            'scope' => 'openid age',
            'state' => 'test-random-string-fixed',
            'code_challenge' => 'mock-challenge-for-test-pkce-verifier-fixed-value',
            'code_challenge_method' => 'S256',
            'nonce' => 'test-random-string-fixed'
        ], $query);
    }

    public function testSessionStoresNonceAndReturnUrl(): void
    {
        $client = new Client($this->config, $this->session, new MockSecurityGenerator());

        $client->beginFlow('https://shop.test/products/1');

        $stored = $this->session->get('aw_oidc_state');
        $this->assertSame('test-random-string-fixed', $stored['nonce']);
        $this->assertSame('https://shop.test/products/1', $stored['return_url']);
    }

    public function testGetReturnUrlBeforeCallbackIsTheStartingPage(): void
    {
        $client = new Client($this->config, $this->session, new MockSecurityGenerator());

        $client->beginFlow('https://shop.test/products/1');

        $this->assertSame('https://shop.test/products/1', $client->getReturnUrl());
    }

    public function testGetReturnUrlWithoutAFlowIsHome(): void
    {
        $client = new Client($this->config, $this->session, new MockSecurityGenerator());

        $this->assertSame('/', $client->getReturnUrl());
    }

    public function testNewFlowReplacesStateAndUrlMatchesSession(): void
    {
        $generator = new StandardSecurityGenerator();
        $client = new Client($this->config, $this->session, $generator);

        $first = $client->beginFlow();
        $second = $client->beginFlow();

        $stored = $this->session->get('aw_oidc_state');
        $query = $this->query($second['url']);
        $this->assertNotSame($first['state'], $second['state']);
        $this->assertSame($second['state'], $stored['state']);
        $this->assertSame($stored['state'], $query['state']);
        $this->assertSame($stored['nonce'], $query['nonce']);
        $this->assertSame($generator->generatePkceChallenge($stored['verifier']), $query['code_challenge']);
    }

    public function testGuardStoresItsReturnUrl(): void
    {
        $client = new RecordingClient($this->config, $this->session, new MockSecurityGenerator());
        $this->expectOutputRegex('/aw-gate-wrapper/');

        $client->guard('https://shop.test/members');

        $this->assertSame('https://shop.test/members', $this->session->get('aw_oidc_state')['return_url']);
        $this->assertSame(1, $client->terminations);
    }
}
