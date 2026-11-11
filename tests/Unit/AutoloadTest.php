<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase
{
    public function testAutoloaderLoadsTheSdkWithoutComposer(): void
    {
        // Runs in a fresh PHP process that loads only autoload.php
        $script = 'require ' . var_export(dirname(__DIR__, 2) . '/autoload.php', true) . ';' . <<<'PHP'
            $loaded = class_exists('AgeWallet\Sdk\Client')
                && class_exists('AgeWallet\Sdk\Renderers\StandardGateRenderer')
                && interface_exists('AgeWallet\Sdk\Interfaces\GateRendererInterface');
            echo $loaded ? 'ok' : 'missing';
            PHP;

        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertSame(['ok'], $output);
    }
}
