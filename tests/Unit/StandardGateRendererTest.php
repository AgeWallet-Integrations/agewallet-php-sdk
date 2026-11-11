<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Unit;

use AgeWallet\Sdk\Renderers\StandardGateRenderer;
use PHPUnit\Framework\TestCase;

class StandardGateRendererTest extends TestCase
{
    public function testAuthUrlIsEscapedInTheButtonLink(): void
    {
        $html = (new StandardGateRenderer())->render('https://app.agewallet.io/user/authorize?a=1&b="2"');

        $this->assertStringContainsString('href="https://app.agewallet.io/user/authorize?a=1&amp;b=&quot;2&quot;"', $html);
    }

    public function testDefaultCopyIsShown(): void
    {
        $html = (new StandardGateRenderer())->render('https://app.agewallet.io/user/authorize');

        $this->assertStringContainsString('You Must Verify Your Age', $html);
        $this->assertStringContainsString('I Agree / Verify', $html);
    }

    public function testButtonTextIsEscaped(): void
    {
        $html = (new StandardGateRenderer())->render('https://app.agewallet.io/user/authorize', ['button_text' => '<b>Go</b>']);

        $this->assertStringContainsString('&lt;b&gt;Go&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Go</b>', $html);
    }

    public function testGuardModeAddsFullPageStyles(): void
    {
        $renderer = new StandardGateRenderer();

        $this->assertStringContainsString('min-height: 100vh', $renderer->render('https://app.agewallet.io/user/authorize', ['is_guard' => true]));
        $this->assertStringNotContainsString('min-height: 100vh', $renderer->render('https://app.agewallet.io/user/authorize'));
    }
}
