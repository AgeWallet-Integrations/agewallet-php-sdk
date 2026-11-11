<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Unit;

use AgeWallet\Sdk\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testIntegerSubjectIsReturnedAsString(): void
    {
        $this->assertSame('123', (new User(true, ['sub' => 123]))->getSubject());
    }

    public function testSubjectIsNullWithoutSub(): void
    {
        $this->assertNull((new User(false))->getSubject());
    }

    public function testClaimsAreReturned(): void
    {
        $claims = ['sub' => 'u1', 'age_verified' => true, 'metadata' => 'order-9001'];

        $this->assertSame($claims, (new User(true, $claims))->getClaims());
    }
}
