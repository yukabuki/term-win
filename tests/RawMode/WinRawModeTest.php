<?php

declare(strict_types=1);

namespace PhpTui\Term\Tests\RawMode;

use PhpTui\Term\RawMode\WinRawMode;
use PhpTui\Term\Tests\Fixtures\FakeWindowsConsole;
use PHPUnit\Framework\TestCase;

final class WinRawModeTest extends TestCase
{
    private const ENABLE_PROCESSED_INPUT = 0x0001;
    private const ENABLE_LINE_INPUT = 0x0002;
    private const ENABLE_ECHO_INPUT = 0x0004;
    private const ENABLE_MOUSE_INPUT = 0x0010;
    private const ENABLE_QUICK_EDIT_MODE = 0x0040;
    private const NOT_RAW_MODE_MASK = self::ENABLE_LINE_INPUT | self::ENABLE_ECHO_INPUT | self::ENABLE_PROCESSED_INPUT | self::ENABLE_QUICK_EDIT_MODE | self::ENABLE_MOUSE_INPUT;

    public function testInitiallyDisabled(): void
    {
        $console = new FakeWindowsConsole();
        $rawMode = WinRawMode::new($console);

        self::assertFalse($rawMode->isEnabled());
    }

    public function testEnableDisable(): void
    {
        $initialMode = 0x00EF;
        $console = new FakeWindowsConsole($initialMode);
        $rawMode = WinRawMode::new($console);

        $rawMode->enable();

        self::assertTrue($rawMode->isEnabled());

        // Verify mode was masked to remove non-raw-mode flags
        $expectedRawMode = $initialMode & ~self::NOT_RAW_MODE_MASK;
        self::assertEquals($expectedRawMode, $console->consoleMode);

        $rawMode->disable();

        self::assertFalse($rawMode->isEnabled());

        // Verify original mode was restored
        self::assertEquals($initialMode, $console->consoleMode);
    }

    public function testDoubleEnableIsIdempotent(): void
    {
        $console = new FakeWindowsConsole();
        $rawMode = WinRawMode::new($console);

        $rawMode->enable();
        $rawMode->enable();

        // getConsoleMode should only have been called once
        $getModeCalls = array_filter($console->calls, fn (array $c): bool => $c['method'] === 'getConsoleMode');
        self::assertCount(1, $getModeCalls);
    }

    public function testDoubleDisableIsIdempotent(): void
    {
        $console = new FakeWindowsConsole();
        $rawMode = WinRawMode::new($console);

        $rawMode->enable();
        $rawMode->disable();
        $rawMode->disable();

        // setConsoleMode should only have been called twice (enable + one disable)
        $setModeCalls = array_filter($console->calls, fn (array $c): bool => $c['method'] === 'setConsoleMode');
        self::assertCount(2, $setModeCalls);
    }

    public function testRawModeRemovesCorrectFlags(): void
    {
        // Start with all flags set
        $initialMode = 0xFFFF;
        $console = new FakeWindowsConsole($initialMode);
        $rawMode = WinRawMode::new($console);

        $rawMode->enable();

        $rawModeValue = $console->consoleMode;

        self::assertEquals(0, $rawModeValue & self::ENABLE_LINE_INPUT);
        self::assertEquals(0, $rawModeValue & self::ENABLE_ECHO_INPUT);
        self::assertEquals(0, $rawModeValue & self::ENABLE_PROCESSED_INPUT);
        self::assertEquals(0, $rawModeValue & self::ENABLE_QUICK_EDIT_MODE);
        self::assertEquals(0, $rawModeValue & self::ENABLE_MOUSE_INPUT);
    }
}
