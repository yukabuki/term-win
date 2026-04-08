<?php

declare(strict_types=1);

namespace PhpTui\Term\Tests\Fixtures;

use PhpTui\Term\WindowsConsoleInterface;

/**
 * In-memory fake for WindowsConsoleInterface used in unit tests.
 * No FFI or Windows API calls are made.
 */
final class FakeWindowsConsole implements WindowsConsoleInterface
{
    public int $consoleMode;

    /** @var array<array{type: 'key', keyDown: bool, virtualKeyCode: int, unicodeChar: int, controlKeyState: int}|array{type: 'mouse', x: int, y: int, buttonState: int, controlKeyState: int, eventFlags: int}|array{type: 'focus', setFocus: bool}> */
    private array $eventQueue = [];

    /** @var array{screenBufferSize: array{x: int, y: int}, cursorPosition: array{x: int, y: int}, windowSize: array{width: int, height: int}, maximumWindowSize: array{x: int, y: int}, attributes: int} */
    private array $screenBufferInfo;

    /** @var array<array{method: string, args: mixed[]}> */
    public array $calls = [];

    public function __construct(int $initialMode = 0x00EF)
    {
        $this->consoleMode = $initialMode;
        $this->screenBufferInfo = [
            'screenBufferSize' => ['x' => 220, 'y' => 9000],
            'cursorPosition' => ['x' => 0, 'y' => 0],
            'windowSize' => ['width' => 220, 'height' => 50],
            'maximumWindowSize' => ['x' => 220, 'y' => 50],
            'attributes' => 7,
        ];
    }

    public function getConsoleMode(): int
    {
        $this->calls[] = ['method' => 'getConsoleMode', 'args' => []];

        return $this->consoleMode;
    }

    public function setConsoleMode(int $mode): void
    {
        $this->calls[] = ['method' => 'setConsoleMode', 'args' => [$mode]];
        $this->consoleMode = $mode;
    }

    public function getConsoleScreenBufferInfo(): array
    {
        $this->calls[] = ['method' => 'getConsoleScreenBufferInfo', 'args' => []];

        return $this->screenBufferInfo;
    }

    /**
     * @param array{screenBufferSize: array{x: int, y: int}, cursorPosition: array{x: int, y: int}, windowSize: array{width: int, height: int}, maximumWindowSize: array{x: int, y: int}, attributes: int} $info
     */
    public function setScreenBufferInfo(array $info): void
    {
        $this->screenBufferInfo = $info;
    }

    public function peekEvents(): int
    {
        $this->calls[] = ['method' => 'peekEvents', 'args' => []];

        return count($this->eventQueue);
    }

    public function readNextEvent(): ?array
    {
        $this->calls[] = ['method' => 'readNextEvent', 'args' => []];

        return array_shift($this->eventQueue);
    }

    /**
     * @param array{type: 'key', keyDown: bool, virtualKeyCode: int, unicodeChar: int, controlKeyState: int}|array{type: 'mouse', x: int, y: int, buttonState: int, controlKeyState: int, eventFlags: int}|array{type: 'focus', setFocus: bool} $event
     */
    public function queueEvent(array $event): void
    {
        $this->eventQueue[] = $event;
    }

    /**
     * @return array{type: 'key', keyDown: bool, virtualKeyCode: int, unicodeChar: int, controlKeyState: int}
     */
    public static function keyDown(int $virtualKeyCode, int $unicodeChar = 0, int $controlKeyState = 0): array
    {
        return ['type' => 'key', 'keyDown' => true, 'virtualKeyCode' => $virtualKeyCode, 'unicodeChar' => $unicodeChar, 'controlKeyState' => $controlKeyState];
    }

    /**
     * @return array{type: 'key', keyDown: bool, virtualKeyCode: int, unicodeChar: int, controlKeyState: int}
     */
    public static function keyUp(int $virtualKeyCode, int $unicodeChar = 0, int $controlKeyState = 0): array
    {
        return ['type' => 'key', 'keyDown' => false, 'virtualKeyCode' => $virtualKeyCode, 'unicodeChar' => $unicodeChar, 'controlKeyState' => $controlKeyState];
    }

    /**
     * @return array{type: 'mouse', x: int, y: int, buttonState: int, controlKeyState: int, eventFlags: int}
     */
    public static function mouseEvent(int $x, int $y, int $buttonState = 0, int $controlKeyState = 0, int $eventFlags = 0): array
    {
        return ['type' => 'mouse', 'x' => $x, 'y' => $y, 'buttonState' => $buttonState, 'controlKeyState' => $controlKeyState, 'eventFlags' => $eventFlags];
    }

    /**
     * @return array{type: 'focus', setFocus: bool}
     */
    public static function focusEvent(bool $setFocus): array
    {
        return ['type' => 'focus', 'setFocus' => $setFocus];
    }
}
