<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Unit;

use AgeWallet\Sdk\Client;
use AgeWallet\Sdk\Tests\Mocks\InMemorySessionHandler;
use AgeWallet\Sdk\Tests\Mocks\MockSecurityGenerator;
use AgeWallet\Sdk\Tests\Mocks\RecordingClient;
use PHPUnit\Framework\TestCase;

class ClientCookieTest extends TestCase
{
    private const SECRET = 'test-hmac';

    protected function setUp(): void
    {
        $_GET = [];
        $_COOKIE = [];
    }

    private function config(array $overrides = []): array
    {
        return array_merge([
            'client_id' => 'test-id',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'http://localhost/callback',
            'hmac_secret' => self::SECRET
        ], $overrides);
    }

    /** Runs a successful callback for user-42 and returns the client that handled it. */
    private function verifyWith(array $config, InMemorySessionHandler $session): RecordingClient
    {
        $session->set('aw_oidc_state', ['state' => 's', 'nonce' => 'n', 'verifier' => 'v', 'return_url' => '/']);
        $_GET = ['state' => 's', 'code' => 'c'];

        $client = new RecordingClient($config, $session, new MockSecurityGenerator());
        $client->responses = [
            ['status' => 200, 'body' => json_encode(['access_token' => 't'])],
            ['status' => 200, 'body' => json_encode(['sub' => 'user-42', 'age_verified' => true])]
        ];
        $client->authenticate();

        return $client;
    }

    private static function signedCookie(array $payload, string $secret): string
    {
        $b64Payload = base64_encode(json_encode($payload));
        return $b64Payload . '.' . hash_hmac('sha256', $b64Payload, $secret);
    }

    public function testSuccessWritesSignedCookie(): void
    {
        $client = $this->verifyWith($this->config(), new InMemorySessionHandler());

        $this->assertCount(1, $client->cookies);
        $cookie = $client->cookies[0];
        $this->assertSame('aw_verified_token', $cookie['name']);
        $this->assertSame('/', $cookie['path']);
        $this->assertSame('', $cookie['domain']);
        $this->assertTrue($cookie['secure']);
        $this->assertTrue($cookie['httponly']);
        $this->assertEqualsWithDelta(time() + 86400, $cookie['expires'], 5);

        [$b64Payload, $signature] = explode('.', $cookie['value']);
        $this->assertSame(hash_hmac('sha256', $b64Payload, self::SECRET), $signature);
        $payload = json_decode(base64_decode($b64Payload), true);
        $this->assertSame('user-42', $payload['sub']);
        $this->assertEqualsWithDelta(time() + 86400, $payload['exp'], 5);
    }

    public function testNoCookieWithoutHmacSecret(): void
    {
        $session = new InMemorySessionHandler();
        $client = $this->verifyWith($this->config(['hmac_secret' => '']), $session);

        $this->assertSame([], $client->cookies);
        $this->assertTrue($session->get('aw_verified'));
    }

    public function testCookieFromEarlierVisitVerifiesNewSession(): void
    {
        $first = $this->verifyWith($this->config(), new InMemorySessionHandler());
        $_GET = [];
        $_COOKIE['aw_verified_token'] = $first->cookies[0]['value'];

        $session = new InMemorySessionHandler();
        $client = new Client($this->config(), $session, new MockSecurityGenerator());
        $user = $client->getUser();

        $this->assertTrue($user->isVerified());
        $this->assertSame('user-42', $user->getSubject());
        $this->assertTrue($session->get('aw_verified'));
    }

    /**
     * @dataProvider rejectedCookies
     */
    public function testRejectedCookieLeavesVisitorUnverified(string $cookie): void
    {
        $_COOKIE['aw_verified_token'] = $cookie;
        $session = new InMemorySessionHandler();
        $client = new Client($this->config(), $session, new MockSecurityGenerator());

        $this->assertFalse($client->isVerified());
        $this->assertNull($session->get('aw_verified'));
    }

    public static function rejectedCookies(): array
    {
        $valid = self::signedCookie(['exp' => time() + 3600, 'sub' => 'u'], self::SECRET);
        $lastChar = substr($valid, -1) === 'a' ? 'b' : 'a';

        return [
            'tampered signature' => [substr($valid, 0, -1) . $lastChar],
            'one segment' => [explode('.', $valid)[0]],
            'three segments' => [$valid . '.extra'],
            'expired' => [self::signedCookie(['exp' => time() - 60, 'sub' => 'u'], self::SECRET)],
            'signed with another secret' => [self::signedCookie(['exp' => time() + 3600, 'sub' => 'u'], 'other-secret')]
        ];
    }

    public function testCookieIgnoredWithoutHmacSecret(): void
    {
        $_COOKIE['aw_verified_token'] = self::signedCookie(['exp' => time() + 3600, 'sub' => 'u'], self::SECRET);
        $client = new Client($this->config(['hmac_secret' => '']), new InMemorySessionHandler(), new MockSecurityGenerator());

        $this->assertFalse($client->isVerified());
    }
}
