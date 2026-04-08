<?php

declare(strict_types=1);

use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\Event\CursorPositionEvent;
use PhpTui\Term\Event\FocusEvent;
use PhpTui\Term\Event\FunctionKeyEvent;
use PhpTui\Term\Event\MouseEvent;
use PhpTui\Term\Event\TerminalResizedEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
use PhpTui\Term\TerminalInformation\Size;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Event Inspector — real-time display of all terminal input events.
 *
 * Useful when:
 *   - Debugging key bindings in your TUI application
 *   - Checking which escape sequences your terminal sends
 *   - Verifying mouse coordinates and button states
 *   - Understanding focus events
 *
 * Controls:
 *   ESC / q / Ctrl+C  →  quit
 *   C                 →  clear the log
 */

$terminal = Terminal::new();
$terminal->execute(
    Actions::alternateScreenEnable(),
    Actions::cursorHide(),
    Actions::enableMouseCapture(),
    Actions::setTitle('Event Inspector'),
);
$terminal->enableRawMode();

/** @var list<array{string, int[], string}> $log */
$log = [];
$totalEvents = 0;

try {
    while (true) {
        $size  = $terminal->info(Size::class);
        $cols  = $size ? $size->cols  : 80;
        $lines = $size ? $size->lines : 24;

        render($terminal, $log, $totalEvents, $cols, $lines);

        while ($event = $terminal->events()->next()) {
            $totalEvents++;

            // Quit
            if ($event instanceof CodedKeyEvent && $event->code === KeyCode::Esc) {
                return;
            }
            if ($event instanceof CharKeyEvent && $event->char === 'q') {
                return;
            }
            if ($event instanceof CharKeyEvent && ord($event->char) === 0x03) { // Ctrl+C
                return;
            }

            // Clear log
            if ($event instanceof CharKeyEvent && ($event->char === 'c' || $event->char === 'C') && $event->modifiers === KeyModifiers::NONE) {
                $log = [];
                continue;
            }

            $log[] = describeEvent($event);
            if (count($log) > 500) {
                array_shift($log);
            }
        }

        usleep(16_000); // ~60 fps
    }
} finally {
    $terminal->execute(
        Actions::disableMouseCapture(),
        Actions::cursorShow(),
        Actions::alternateScreenDisable(),
    );
    $terminal->disableRawMode();
}

/**
 * @param list<array{string, int[], string}> $log
 */
function render(Terminal $terminal, array $log, int $totalEvents, int $cols, int $lines): void
{
    $terminal->queue(Actions::moveCursor(1, 1), Actions::clear(ClearType::All));

    // ── Header bar ──────────────────────────────────────────────────────────
    $title = ' ⚡ Event Inspector ';
    $hint  = ' ESC/q = quit   C = clear ';
    $pad   = max(0, $cols - mb_strlen($title) - mb_strlen($hint));
    $terminal->queue(
        Actions::moveCursor(1, 1),
        Actions::setRgbBackgroundColor(25, 55, 110),
        Actions::setRgbForegroundColor(180, 210, 255),
        Actions::bold(true),
        Actions::printString($title . str_repeat(' ', $pad) . $hint),
        Actions::reset(),
    );

    // ── Event log (newest on top) ────────────────────────────────────────────
    $maxVisible = max(0, $lines - 2);
    $visible    = array_reverse(array_slice($log, -$maxVisible));

    $row = 2;
    foreach ($visible as [$type, $rgb, $desc]) {
        if ($row >= $lines) {
            break;
        }
        $terminal->queue(Actions::moveCursor($row, 1));
        [$r, $g, $b] = $rgb;
        $terminal->queue(
            Actions::setRgbBackgroundColor($r, $g, $b),
            Actions::setRgbForegroundColor(15, 15, 15),
            Actions::bold(true),
            Actions::printString(sprintf(' %-6s ', $type)),
            Actions::reset(),
            Actions::printString('  '),
            Actions::printString(mb_substr($desc, 0, $cols - 11)),
        );
        $row++;
    }

    // ── Footer bar ───────────────────────────────────────────────────────────
    $footer = sprintf(' Total: %d  │  %d×%d  │  Mouse captured ', $totalEvents, $cols, $lines);
    $terminal->queue(
        Actions::moveCursor($lines, 1),
        Actions::setRgbBackgroundColor(30, 30, 30),
        Actions::setRgbForegroundColor(130, 130, 130),
        Actions::printString(str_pad(mb_substr($footer, 0, $cols), $cols)),
        Actions::reset(),
    );

    $terminal->flush();
}

/**
 * @return array{string, int[], string}
 */
function describeEvent(Event $event): array
{
    if ($event instanceof CharKeyEvent) {
        $display = match (true) {
            ord($event->char) === 0x00 => 'NUL',
            ord($event->char) === 0x03 => 'ETX (Ctrl+C)',
            ord($event->char) === 0x08 => 'BS',
            ord($event->char) === 0x09 => 'TAB',
            ord($event->char) === 0x0D => 'CR',
            ord($event->char) === 0x1B => 'ESC',
            ord($event->char) === 0x7F => 'DEL',
            ord($event->char) < 0x20   => sprintf('\\x%02X', ord($event->char)),
            default                    => $event->char,
        };
        $mods = $event->modifiers !== KeyModifiers::NONE
            ? ' +' . KeyModifiers::toString($event->modifiers) : '';

        return ['KEY', [0, 180, 200], sprintf("'%s'%s", $display, $mods)];
    }

    if ($event instanceof CodedKeyEvent) {
        $mods = $event->modifiers !== KeyModifiers::NONE
            ? ' +' . KeyModifiers::toString($event->modifiers) : '';

        return ['KEY', [0, 180, 200], $event->code->name . $mods];
    }

    if ($event instanceof FunctionKeyEvent) {
        $mods = $event->modifiers !== KeyModifiers::NONE
            ? ' +' . KeyModifiers::toString($event->modifiers) : '';

        return ['KEY', [0, 180, 200], 'F' . $event->number . $mods];
    }

    if ($event instanceof MouseEvent) {
        return ['MOUSE', [200, 160, 0], sprintf(
            '%-16s  btn=%-8s  col=%-5d  row=%d',
            $event->kind->name,
            $event->button->name,
            $event->column,
            $event->row,
        )];
    }

    if ($event instanceof FocusEvent) {
        return ['FOCUS', [0, 180, 80], $event->gained ? 'Gained' : 'Lost'];
    }

    if ($event instanceof TerminalResizedEvent) {
        return ['RESIZE', [180, 0, 200], 'Terminal resized'];
    }

    if ($event instanceof CursorPositionEvent) {
        return ['CURSOR', [60, 120, 220], sprintf('col=%d  row=%d', $event->column, $event->row)];
    }

    return ['OTHER', [120, 120, 120], $event->__toString()];
}
