<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Unit;

use AgeWallet\Sdk\Client;
use AgeWallet\Sdk\Exceptions\AgeWalletException;
use AgeWallet\Sdk\Tests\Mocks\InMemorySessionHandler;
use AgeWallet\Sdk\Tests\Mocks\MockSecurityGenerator;
use PHPUnit\Framework\TestCase;

class ClientConfigTest extends TestCase
{
    private const CONFIG = [
        'client_id' => 'test-id',
        'client_secret' => 'test-secret',
        'redirect_uri' => 'http://localhost/callback'
    ];

    private function client(array $config): Client
    {
        return new Client($config, new InMemorySessionHandler(), new MockSecurityGenerator());
    }

    /**
     * @dataProvider requiredKeys
     */
    public function testMissingRequiredKeyIsRejected(string $key): void
    {
        $config = self::CONFIG;
        unset($config[$key]);

        $this->expectException(AgeWalletException::class);
        $this->expectExceptionMessage("Missing configuration key: {$key}");

        $this->client($config);
    }

    public static function requiredKeys(): array
    {
        return [
            'client_id' => ['client_id'],
            'client_secret' => ['client_secret'],
            'redirect_uri' => ['redirect_uri']
        ];
    }

    public function testNonStringMetadataIsRejected(): void
    {
        $this->expectException(AgeWalletException::class);
        $this->expectExceptionMessage('metadata must be a string.');

        $this->client(self::CONFIG + ['metadata' => 123]);
    }

    public function testMetadataAtTheByteLimitIsAccepted(): void
    {
        $metadata = str_repeat('a', Client::METADATA_MAX_BYTES);
        $client = $this->client(self::CONFIG);

        $client->setMetadata($metadata);

        parse_str((string) parse_url($client->beginFlow()['url'], PHP_URL_QUERY), $query);
        $this->assertSame($metadata, $query['metadata']);
    }

    public function testMetadataLimitCountsBytesNotCharacters(): void
    {
        $atLimit = str_repeat('é', intdiv(Client::METADATA_MAX_BYTES, 2));
        $client = $this->client(self::CONFIG);
        $client->setMetadata($atLimit);

        $this->expectException(AgeWalletException::class);
        $this->expectExceptionMessage('exceeds');

        $client->setMetadata($atLimit . 'a');
    }
}
