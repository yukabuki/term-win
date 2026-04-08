<?php

declare(strict_types=1);

namespace PhpTui\Term;

interface WindowsConsoleInterface
{
    public function getConsoleMode(): int;

    public function setConsoleMode(int $mode): void;

    /**
     * @return array{screenBufferSize: array{x: int, y: int}, cursorPosition: array{x: int, y: int}, windowSize: array{width: int, height: int}, maximumWindowSize: array{x: int, y: int}, attributes: int}
     */
    public function getConsoleScreenBufferInfo(): array;

    public function peekEvents(): int;

    /**
     * Returns the next console input event as a PHP array, or null if the event
     * type is not handled (e.g. MENU_EVENT, WINDOW_BUFFER_SIZE_EVENT).
     *
     * @return array{type: 'key', keyDown: bool, virtualKeyCode: int, unicodeChar: int, controlKeyState: int}|array{type: 'mouse', x: int, y: int, buttonState: int, controlKeyState: int, eventFlags: int}|array{type: 'focus', setFocus: bool}|null
     */
    public function readNextEvent(): ?array;
}
