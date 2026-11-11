<?php

declare(strict_types=1);

namespace AgeWallet\Sdk\Tests\Unit;

use AgeWallet\Sdk\Storage\NativeSessionHandler;
use PHPUnit\Framework\TestCase;

class NativeSessionHandlerTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testValuesRoundTripThroughThePhpSession(): void
    {
        // No cookie or cache headers, so session_start() works in the CLI
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');
        ini_set('session.save_path', sys_get_temp_dir());

        $handler = new NativeSessionHandler();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());

        $handler->set('aw_test', ['a' => 1]);
        $this->assertTrue($handler->has('aw_test'));
        $this->assertSame(['a' => 1], $handler->get('aw_test'));
        $this->assertSame(['a' => 1], $_SESSION['aw_test']);

        $handler->remove('aw_test');
        $this->assertFalse($handler->has('aw_test'));
        $this->assertSame('fallback', $handler->get('aw_test', 'fallback'));

        session_destroy();
    }
}
