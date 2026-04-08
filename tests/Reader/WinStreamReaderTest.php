<?php

declare(strict_types=1);

namespace PhpTui\Term\Tests\Reader;

use PhpTui\Term\Reader\WinStreamReader;
use PhpTui\Term\Tests\Fixtures\FakeWindowsConsole;
use PHPUnit\Framework\TestCase;

final class WinStreamReaderTest extends TestCase
{
    public function testReturnsNullWhenNoEvents(): void
    {
        $console = new FakeWindowsConsole();
        $reader = WinStreamReader::new($console);

        self::assertNull($reader->read());
    }

    public function testReturnsNullThenNullOnPendingFlag(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyDown(0x41, ord('A'))); // 'A'
        $reader = WinStreamReader::new($console);

        $first = $reader->read();
        self::assertEquals('A', $first);

        // Second read must return null (pendingNull flag)
        $second = $reader->read();
        self::assertNull($second);
    }

    public function testAsciiCharKeyEvent(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyDown(0x48, ord('H'))); // 'H'
        $reader = WinStreamReader::new($console);

        self::assertEquals('H', $reader->read());
    }

    public function testLowercaseCharKeyEvent(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyDown(0x41, ord('a'))); // 'a'
        $reader = WinStreamReader::new($console);

        self::assertEquals('a', $reader->read());
    }

    public function testUnicodeCharEvent(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyDown(0xE9, 0xE9)); // 'é' = U+00E9
        $reader = WinStreamReader::new($console);

        self::assertEquals('é', $reader->read());
    }

    public function testMultibyteUnicodeChar(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyDown(0x00, 0x4E2D)); // '中' = U+4E2D
        $reader = WinStreamReader::new($console);

        self::assertEquals('中', $reader->read());
    }

    public function testCtrlC(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyDown(0x43, 0x03)); // Ctrl+C = ETX
        $reader = WinStreamReader::new($console);

        self::assertEquals("\x03", $reader->read());
    }

    public function testKeyUpIsIgnored(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyUp(0x41, ord('A'))); // key-up 'A'
        $reader = WinStreamReader::new($console);

        self::assertNull($reader->read());
    }

    public function testModifierOnlyKeyIsIgnored(): void
    {
        // Ctrl key alone has unicodeChar == 0
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyDown(0x11, 0)); // VK_CONTROL
        $reader = WinStreamReader::new($console);

        self::assertNull($reader->read());
    }

    public function testFunctionKeyF1(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyDown(0x70, 0)); // VK_F1
        $reader = WinStreamReader::new($console);

        self::assertEquals("\x1B[11~", $reader->read());
    }

    public function testFunctionKeyF12(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::keyDown(0x7B, 0)); // VK_F12
        $reader = WinStreamReader::new($console);

        self::assertEquals("\x1B[24~", $reader->read());
    }

    public function testArrowKeys(): void
    {
        $cases = [
            [0x25, "\x1B[D"], // Left
            [0x26, "\x1B[A"], // Up
            [0x27, "\x1B[C"], // Right
            [0x28, "\x1B[B"], // Down
        ];

        foreach ($cases as [$vk, $expected]) {
            $console = new FakeWindowsConsole();
            $console->queueEvent(FakeWindowsConsole::keyDown($vk, 0));
            $reader = WinStreamReader::new($console);

            self::assertEquals($expected, $reader->read(), sprintf('VK 0x%02X', $vk));
        }
    }

    public function testNavigationKeys(): void
    {
        $cases = [
            [0x24, "\x1B[H"], // Home
            [0x23, "\x1B[F"], // End
            [0x21, "\x1B[5~"], // Page Up
            [0x22, "\x1B[6~"], // Page Down
            [0x2D, "\x1B[2~"], // Insert
            [0x2E, "\x1B[3~"], // Delete
            [0x08, "\x7F"],    // Backspace
        ];

        foreach ($cases as [$vk, $expected]) {
            $console = new FakeWindowsConsole();
            $console->queueEvent(FakeWindowsConsole::keyDown($vk, 0));
            $reader = WinStreamReader::new($console);

            self::assertEquals($expected, $reader->read(), sprintf('VK 0x%02X', $vk));
        }
    }

    public function testMouseLeftButtonPress(): void
    {
        $console = new FakeWindowsConsole();
        // Left button press at (5, 10) with no modifiers, eventFlags=0
        $console->queueEvent(FakeWindowsConsole::mouseEvent(4, 9, 0x0001, 0, 0));
        $reader = WinStreamReader::new($console);

        // x=5, y=10, button=0 (left), press = uppercase M
        self::assertEquals("\x1B[<0;5;10M", $reader->read());
    }

    public function testMouseLeftButtonRelease(): void
    {
        $console = new FakeWindowsConsole();
        // First a press to set lastPressedButton
        $console->queueEvent(FakeWindowsConsole::mouseEvent(4, 9, 0x0001, 0, 0));
        // Then a release (buttonState=0, eventFlags=0)
        $console->queueEvent(FakeWindowsConsole::mouseEvent(4, 9, 0, 0, 0));
        $reader = WinStreamReader::new($console);

        $reader->read(); // press
        $reader->read(); // pending null
        // Now the release
        self::assertEquals("\x1B[<0;5;10m", $reader->read());
    }

    public function testMouseMovement(): void
    {
        $console = new FakeWindowsConsole();
        // Move without button (eventFlags=MOUSE_MOVED=1, buttonState=0)
        $console->queueEvent(FakeWindowsConsole::mouseEvent(9, 4, 0, 0, 1));
        $reader = WinStreamReader::new($console);

        self::assertEquals("\x1B[<35;10;5m", $reader->read());
    }

    public function testMouseScrollDown(): void
    {
        $console = new FakeWindowsConsole();
        // Scroll down: eventFlags=MOUSE_WHEELED=4, positive delta in high 16 bits
        // Positive delta (e.g. 120) = scroll down in Windows convention
        $buttonState = (120 << 16); // positive wheel delta
        $console->queueEvent(FakeWindowsConsole::mouseEvent(0, 0, $buttonState, 0, 4));
        $reader = WinStreamReader::new($console);

        self::assertEquals("\x1B[<64;1;1M", $reader->read());
    }

    public function testMouseScrollUp(): void
    {
        $console = new FakeWindowsConsole();
        // Scroll up: negative delta in high 16 bits (signed 16-bit negative = bit 15 set)
        $wheelDelta16 = 0xFF88; // -120 as unsigned 16-bit
        $buttonState = ($wheelDelta16 << 16);
        $console->queueEvent(FakeWindowsConsole::mouseEvent(0, 0, $buttonState, 0, 4));
        $reader = WinStreamReader::new($console);

        self::assertEquals("\x1B[<65;1;1M", $reader->read());
    }

    public function testFocusGained(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::focusEvent(true));
        $reader = WinStreamReader::new($console);

        self::assertEquals("\x1B[I", $reader->read());
    }

    public function testFocusLost(): void
    {
        $console = new FakeWindowsConsole();
        $console->queueEvent(FakeWindowsConsole::focusEvent(false));
        $reader = WinStreamReader::new($console);

        self::assertEquals("\x1B[O", $reader->read());
    }

    public function testRightButtonPress(): void
    {
        $console = new FakeWindowsConsole();
        // Right button (RIGHTMOST_BUTTON_PRESSED = 0x0002), at (0,0), eventFlags=0
        $console->queueEvent(FakeWindowsConsole::mouseEvent(0, 0, 0x0002, 0, 0));
        $reader = WinStreamReader::new($console);

        // button=2 (right), press = uppercase M
        self::assertEquals("\x1B[<2;1;1M", $reader->read());
    }
}
