<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Unit;

use AgeWallet\Sdk\Client;
use AgeWallet\Sdk\Exceptions\AgeWalletException;
use AgeWallet\Sdk\Tests\Mocks\InMemorySessionHandler;
use AgeWallet\Sdk\Tests\Mocks\MockSecurityGenerator;
use AgeWallet\Sdk\Tests\Mocks\RecordingClient;
use PHPUnit\Framework\TestCase;

class ClientAuthenticateTest extends TestCase
{
    private InMemorySessionHandler $session;
    private RecordingClient $client;

    protected function setUp(): void
    {
        $this->session = new InMemorySessionHandler();
        $this->client = new RecordingClient([
            'client_id' => 'test-id',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'http://localhost/callback',
            'hmac_secret' => 'test-hmac'
        ], $this->session, new MockSecurityGenerator());

        // A flow started by beginFlow(), and the callback that returns to it
        $this->session->set('aw_oidc_state', [
            'state' => 'valid-state',
            'nonce' => 'n',
            'verifier' => 'stored-verifier',
            'return_url' => 'https://shop.test/products/1'
        ]);
        $_GET = ['state' => 'valid-state', 'code' => 'valid-code'];
        $_COOKIE = [];
    }

    private function queueReplies(array $userInfo): void
    {
        $this->client->responses = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'mock-token', 'token_type' => 'Bearer'])],
            ['status' => 200, 'body' => json_encode($userInfo)]
        ];
    }

    private function assertAuthenticateFails(string $message): void
    {
        try {
            $this->client->authenticate();
            $this->fail('Expected AgeWalletException');
        } catch (AgeWalletException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->assertNull($this->session->get('aw_verified'));
        $this->assertNull($this->session->get('aw_claims'));
        $this->assertSame([], $this->client->cookies);
        $this->assertFalse($this->client->isVerified());
    }

    // ---- Requests sent ----

    public function testTokenRequestSendsCodeAndStoredVerifier(): void
    {
        $this->queueReplies(['sub' => 'u1', 'age_verified' => true]);

        $this->client->authenticate();

        $request = $this->client->requests[0];
        $this->assertSame(Client::TOKEN_ENDPOINT, $request['url']);
        $this->assertTrue($request['options'][CURLOPT_POST]);
        parse_str($request['options'][CURLOPT_POSTFIELDS], $body);
        $this->assertSame([
            'grant_type' => 'authorization_code',
            'client_id' => 'test-id',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'http://localhost/callback',
            'code' => 'valid-code',
            'code_verifier' => 'stored-verifier'
        ], $body);
    }

    public function testUserInfoRequestSendsAccessToken(): void
    {
        $this->queueReplies(['sub' => 'u1', 'age_verified' => true]);

        $this->client->authenticate();

        $request = $this->client->requests[1];
        $this->assertSame(Client::USERINFO_ENDPOINT, $request['url']);
        $this->assertSame(['Authorization: Bearer mock-token'], $request['options'][CURLOPT_HTTPHEADER]);
    }

    public function testSuccessStoresClaimsAndMetadata(): void
    {
        $claims = ['sub' => 'u1', 'age_verified' => true, 'metadata' => 'order-9001'];
        $this->queueReplies($claims);

        $user = $this->client->authenticate();

        $this->assertTrue($user->isVerified());
        $this->assertSame($claims, $this->session->get('aw_claims'));
        $this->assertSame('order-9001', $this->client->getMetadata());
    }

    // ---- Verification decision ----

    /**
     * @dataProvider rejectedAgeClaims
     */
    public function testRejectsUnlessAgeVerifiedIsTrue(array $userInfo): void
    {
        $this->queueReplies($userInfo);

        $this->assertAuthenticateFails('Age verification requirements were not met.');
    }

    public static function rejectedAgeClaims(): array
    {
        return [
            'false' => [['sub' => 'u1', 'age_verified' => false]],
            'missing' => [['sub' => 'u1']],
            'string "true"' => [['sub' => 'u1', 'age_verified' => 'true']],
            'integer 1' => [['sub' => 'u1', 'age_verified' => 1]]
        ];
    }

    public function testTokenEndpointFailureStopsTheFlow(): void
    {
        $this->client->responses = [['status' => 500, 'body' => '']];

        $this->assertAuthenticateFails('Token exchange failed. HTTP 500');
        $this->assertCount(1, $this->client->requests);
    }

    public function testUserInfoFailureStopsTheFlow(): void
    {
        $this->client->responses = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'mock-token'])],
            ['status' => 401, 'body' => '']
        ];

        $this->assertAuthenticateFails('User info fetch failed. HTTP 401');
    }

    // ---- Callback checks ----

    public function testCallbackErrorIsReported(): void
    {
        $_GET = ['state' => 'valid-state', 'error' => 'access_denied', 'error_description' => 'The user denied the request'];

        $this->assertAuthenticateFails('Verification failed: The user denied the request');
        $this->assertSame([], $this->client->requests);
    }

    public function testCallbackErrorWithoutDescriptionReportsTheCode(): void
    {
        $_GET = ['state' => 'valid-state', 'error' => 'server_error'];

        $this->assertAuthenticateFails('Verification failed: server_error');
    }

    public function testMissingCodeIsRejected(): void
    {
        $_GET = ['state' => 'valid-state'];

        $this->assertAuthenticateFails('Missing authorization code.');
        $this->assertSame([], $this->client->requests);
    }

    public function testMissingStateIsRejected(): void
    {
        $_GET = ['code' => 'valid-code'];

        $this->assertAuthenticateFails('Invalid state parameter.');
        $this->assertSame([], $this->client->requests);
    }
}
