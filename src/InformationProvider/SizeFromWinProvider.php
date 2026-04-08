<?php

declare(strict_types=1);

namespace PhpTui\Term\InformationProvider;

use PhpTui\Term\InformationProvider;
use PhpTui\Term\TerminalInformation\Size;
use PhpTui\Term\TerminalInformation;
use PhpTui\Term\WindowsConsole;
use PhpTui\Term\WindowsConsoleInterface;

final class SizeFromWinProvider implements InformationProvider
{
    private WindowsConsoleInterface $windowsConsole;

    private function __construct(?WindowsConsoleInterface $windowsConsole = null)
    {
        $this->windowsConsole = $windowsConsole ?? WindowsConsole::getInstance();
    }

    public static function new(?WindowsConsoleInterface $windowsConsole = null): self
    {
        return new self($windowsConsole);
    }

    public function for(string $classFqn): ?TerminalInformation
    {
        if ($classFqn !== Size::class) {
            return null;
        }

        $out = $this->windowsConsole->getConsoleScreenBufferInfo();

        /**
         * @phpstan-ignore-next-line */
        return $this->parse($out);
    }

    /**
     * @param array{screenBufferSize: array{x: int, y: int}, cursorPosition: array{x: int, y: int}, windowSize: array{width: int, height: int}, maximumWindowSize: array{x: int, y: int}, attributes: int} $out
     */
    private function parse(array $out): Size
    {
        // Use the visible window size, not the screen buffer size which includes scrollback
        return new Size(
            max(0, (int) $out['windowSize']['height']),
            max(0, (int) $out['windowSize']['width'])
        );
    }
}
