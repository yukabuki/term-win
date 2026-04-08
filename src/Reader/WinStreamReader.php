<?php

declare(strict_types=1);

namespace PhpTui\Term\Reader;

use PhpTui\Term\Reader;
use PhpTui\Term\WindowsConsole;
use PhpTui\Term\WindowsConsoleInterface;

final class WinStreamReader implements Reader
{
    // https://learn.microsoft.com/en-us/windows/console/mouse-event-record-str
    private const FROM_LEFT_1ST_BUTTON_PRESSED = 0x0001;
    private const RIGHTMOST_BUTTON_PRESSED = 0x0002;
    private const FROM_LEFT_2ND_BUTTON_PRESSED = 0x0004;
    private const MOUSE_MOVED = 0x0001;
    private const MOUSE_WHEELED = 0x0004;
    private const WHEEL_SIGN_BIT = 0x8000;

    // https://learn.microsoft.com/en-us/windows/console/key-event-record-str
    private const ALT_PRESSED = 0x0002;
    private const CTRL_PRESSED = 0x0008;
    private const SHIFT_PRESSED = 0x0010;

    // FN keys - If there are more without ascii representation, we can add them here
    // https://learn.microsoft.com/en-us/windows/win32/inputdev/virtual-key-codes
    private const VK_ESCAPES = [
        0x70 => "\x1B[11~", // VK_F1
        0x71 => "\x1B[12~", // VK_F2
        0x72 => "\x1B[13~", // VK_F3
        0x73 => "\x1B[14~", // VK_F4
        0x74 => "\x1B[15~", // VK_F5
        0x75 => "\x1B[17~", // VK_F6
        0x76 => "\x1B[18~", // VK_F7
        0x77 => "\x1B[19~", // VK_F8
        0x78 => "\x1B[20~", // VK_F9
        0x79 => "\x1B[21~", // VK_F10
        0x7A => "\x1B[23~", // VK_F11
        0x7B => "\x1B[24~", // VK_F12
        0x7C => "\x1B[25~", // VK_F13
        0x7D => "\x1B[26~", // VK_F14
        0x7E => "\x1B[27~", // VK_F15
        0x7F => "\x1B[28~", // VK_F16
        0x80 => "\x1B[29~", // VK_F17
        0x81 => "\x1B[30~", // VK_F18
        0x82 => "\x1B[31~", // VK_F19
        0x83 => "\x1B[32~", // VK_F20
        0x84 => "\x1B[33~", // VK_F21
        0x85 => "\x1B[34~", // VK_F22
        0x86 => "\x1B[35~", // VK_F23
        0x87 => "\x1B[36~", // VK_F24
        0x08 => "\x7F",     // VK_BACKSPACE
        0x25 => "\x1B[D",   // VK_LEFT
        0x26 => "\x1B[A",   // VK_UP
        0x27 => "\x1B[C",   // VK_RIGHT
        0x28 => "\x1B[B",   // VK_DOWN
        0x2A => "\x1B[32~", // VK_PRINT
        0x91 => "\x1B[33~", // VK_SCROLL
        0x13 => "\x1B[34~", // VK_PAUSE
        0x2D => "\x1B[2~",  // VK_INSERT
        0x24 => "\x1B[H",   // VK_HOME
        0x21 => "\x1B[5~",  // VK_PRIOR (Page Up)
        0x2E => "\x1B[3~",  // VK_DELETE
        0x23 => "\x1B[F",   // VK_END
        0x22 => "\x1B[6~",  // VK_NEXT (Page Down)
    ];

    private bool $pendingNull = false;

    private int $lastPressedButton = 0;

    private int $lastModifierState = 0;

    private WindowsConsoleInterface $windowsConsole;

    private function __construct(?WindowsConsoleInterface $windowsConsole = null)
    {
        $this->windowsConsole = $windowsConsole ?? WindowsConsole::getInstance();
    }

    public static function new(?WindowsConsoleInterface $windowsConsole = null): self
    {
        return new self($windowsConsole);
    }

    public function read(): ?string
    {
        // We only parse the stream when we return a null.
        // With Key events, we need to set a flag to return null on the next loop to mimic unix behavior.
        // With Mouse events, we need to return null on the next loop,
        // or we are stuck waiting for something else to trigger this.
        // If we have a pending null return, return it and clear the flag
        if ($this->pendingNull) {
            $this->pendingNull = false;

            return null;
        }

        if ($this->windowsConsole->peekEvents() < 1) {
            return null;
        }

        $event = $this->windowsConsole->readNextEvent();

        if ($event === null) {
            return null;
        }

        switch ($event['type']) {
            case 'key':
                if ($event['keyDown']) {
                    if (isset(self::VK_ESCAPES[$event['virtualKeyCode']])) {
                        return $this->sendKey(self::VK_ESCAPES[$event['virtualKeyCode']]);
                    }

                    // Skip modifier-only key presses (no character produced)
                    if ($event['unicodeChar'] === 0) {
                        break;
                    }

                    return $this->sendKey(self::codePointToUtf8($event['unicodeChar']));
                }
                break;

            case 'mouse':
                return $this->calculateSGR($event);

            case 'focus':
                $this->pendingNull = true;

                return $event['setFocus'] ? "\x1B[I" : "\x1B[O";
        }

        return null;
    }

    /**
     * @param array{x: int, y: int, buttonState: int, controlKeyState: int, eventFlags: int} $mouseEvent
     */
    private function calculateSGR(array $mouseEvent): ?string
    {
        $x = $mouseEvent['x'] + 1;
        $y = $mouseEvent['y'] + 1;

        $this->pendingNull = true;

        $button = 0;
        $modifierState = 0;

        if ($mouseEvent['buttonState'] & self::FROM_LEFT_2ND_BUTTON_PRESSED) {
            $button = 1; // Middle button
        } elseif ($mouseEvent['buttonState'] & self::RIGHTMOST_BUTTON_PRESSED) {
            $button = 2; // Right button
        }

        if ($mouseEvent['controlKeyState'] & self::SHIFT_PRESSED) {
            $modifierState |= 4;
        }
        if ($mouseEvent['controlKeyState'] & self::ALT_PRESSED) {
            $modifierState |= 8;
        }
        if ($mouseEvent['controlKeyState'] & self::CTRL_PRESSED) {
            $modifierState |= 16;
        }

        if ($mouseEvent['eventFlags'] === self::MOUSE_MOVED) {
            $buttonState = $mouseEvent['buttonState'];
            if ($buttonState & (self::FROM_LEFT_1ST_BUTTON_PRESSED | self::RIGHTMOST_BUTTON_PRESSED | self::FROM_LEFT_2ND_BUTTON_PRESSED)) {
                if ($buttonState & self::FROM_LEFT_1ST_BUTTON_PRESSED) {
                    $button = 32; // Left button drag
                } elseif ($buttonState & self::FROM_LEFT_2ND_BUTTON_PRESSED) {
                    $button = 33; // Middle button drag
                } elseif ($buttonState & self::RIGHTMOST_BUTTON_PRESSED) {
                    $button = 34; // Right button drag
                }
                // Add the stored modifier state from when the drag started
                $button += $this->lastModifierState;

                return sprintf("\x1B[<%d;%d;%dM", $button, $x, $y);
            } else {
                // Movement without buttons
                return sprintf("\x1B[<%d;%d;%dm", 35, $x, $y);
            }
        } elseif ($mouseEvent['eventFlags'] === self::MOUSE_WHEELED) {
            // High 16 bits of buttonState contain the signed wheel delta
            $wheelDelta = ($mouseEvent['buttonState'] >> 16) & 0xFFFF;
            $isNegative = ($wheelDelta & self::WHEEL_SIGN_BIT) !== 0;

            if ($isNegative) {
                // Scroll up (negative delta in Windows = scroll up)
                return sprintf("\x1B[<%d;%d;%dM", 65 + $modifierState, $x, $y);
            } else {
                // Scroll down
                return sprintf("\x1B[<%d;%d;%dM", 64 + $modifierState, $x, $y);
            }
        } elseif ($mouseEvent['eventFlags'] === 0) {
            $button += $modifierState;

            if ($mouseEvent['buttonState'] === 0) {
                // Button release - use lowercase 'm' with the last pressed button and modifiers
                return sprintf("\x1B[<%d;%d;%dm", $this->lastPressedButton, $x, $y);
            } else {
                // Button press - store both button and modifier state
                $this->lastPressedButton = $button;
                $this->lastModifierState = $modifierState;

                return sprintf("\x1B[<%d;%d;%dM", $button, $x, $y);
            }
        }

        return null;
    }

    private function sendKey(string $key): string
    {
        $this->pendingNull = true;

        return $key;
    }

    /**
     * Convert a UTF-16 BMP code point to a UTF-8 string without requiring mbstring.
     */
    private static function codePointToUtf8(int $codePoint): string
    {
        if ($codePoint < 0x80) {
            return chr($codePoint);
        }

        if ($codePoint < 0x800) {
            return chr(0xC0 | ($codePoint >> 6)) . chr(0x80 | ($codePoint & 0x3F));
        }

        return chr(0xE0 | ($codePoint >> 12))
            . chr(0x80 | (($codePoint >> 6) & 0x3F))
            . chr(0x80 | ($codePoint & 0x3F));
    }
}
