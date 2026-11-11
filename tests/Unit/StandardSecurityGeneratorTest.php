<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Unit;

use AgeWallet\Sdk\Security\StandardSecurityGenerator;
use PHPUnit\Framework\TestCase;

class StandardSecurityGeneratorTest extends TestCase
{
    public function testPkceChallengeMatchesRfc7636Example(): void
    {
        // RFC 7636 Appendix B
        $this->assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            (new StandardSecurityGenerator())->generatePkceChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk')
        );
    }

    public function testPkceVerifierMeetsRfc7636(): void
    {
        $verifier = (new StandardSecurityGenerator())->generatePkceVerifier();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier);
    }

    public function testRandomStringIsHexAndDiffersBetweenCalls(): void
    {
        $generator = new StandardSecurityGenerator();

        $first = $generator->generateRandomString(16);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
        $this->assertNotSame($first, $generator->generateRandomString(16));
    }
}
