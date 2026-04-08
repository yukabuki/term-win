<?php

declare(strict_types=1);

namespace PhpTui\Term\RawMode;

use PhpTui\Term\RawMode;
use PhpTui\Term\WindowsConsole;
use PhpTui\Term\WindowsConsoleInterface;

final class WinRawMode implements RawMode
{
    // https://learn.microsoft.com/en-us/windows/console/setconsolemode
    private const ENABLE_PROCESSED_INPUT = 0x0001;
    private const ENABLE_LINE_INPUT = 0x0002;
    private const ENABLE_ECHO_INPUT = 0x0004;
    private const ENABLE_MOUSE_INPUT = 0x0010;
    private const ENABLE_QUICK_EDIT_MODE = 0x0040;
    // Mouse input is disabled in raw mode to match Linux/stty behavior: no mouse
    // events are delivered unless the caller explicitly calls enableMouseCapture().
    private const NOT_RAW_MODE_MASK = self::ENABLE_LINE_INPUT | self::ENABLE_ECHO_INPUT | self::ENABLE_PROCESSED_INPUT | self::ENABLE_QUICK_EDIT_MODE | self::ENABLE_MOUSE_INPUT;

    private ?int $originalSettings = null;

    private WindowsConsoleInterface $windowsConsole;

    public function __construct(?WindowsConsoleInterface $windowsConsole = null)
    {
        $this->windowsConsole = $windowsConsole ?? WindowsConsole::getInstance();
    }

    public static function new(?WindowsConsoleInterface $windowsConsole = null): self
    {
        return new self($windowsConsole);
    }

    // https://github.com/crossterm-rs/crossterm/blob/master/src/terminal/sys/windows.rs#L31
    public function enable(): void
    {
        if ($this->isEnabled()) {
            return;
        }

        $mode = $this->windowsConsole->getConsoleMode();

        $this->originalSettings = $mode;

        $newMode = $mode &= ~self::NOT_RAW_MODE_MASK;

        $this->windowsConsole->setConsoleMode($newMode);
    }

    public function disable(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        /**
         * @phpstan-ignore-next-line */
        $this->windowsConsole->setConsoleMode($this->originalSettings);

        $this->originalSettings = null;
    }

    public function isEnabled(): bool
    {
        return $this->originalSettings !== null;
    }
}
