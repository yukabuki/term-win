<?php

declare(strict_types=1);

use PhpTui\Term\Action;
use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Colors;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\EventProvider\ArrayEventProvider;
use PhpTui\Term\InformationProvider\ClosureInformationProvider;
use PhpTui\Term\KeyCode;
use PhpTui\Term\Painter\ArrayPainter;
use PhpTui\Term\RawMode\TestRawMode;
use PhpTui\Term\Terminal;
use PhpTui\Term\TerminalInformation;
use PhpTui\Term\TerminalInformation\Size;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Color & Style Reference + Testability Demo.
 *
 * This example serves two purposes:
 *
 *   1. Visual showcase of all rendering capabilities (colors, text styles,
 *      RGB gradients, box drawing) — press any key to exit.
 *
 *   2. Testability pattern: the `drawShowcase()` function is decoupled from
 *      the real terminal. Run it with ArrayPainter or the real terminal — the
 *      same function works for both. Scroll to the bottom to see how.
 */

// ── Run with the real terminal ────────────────────────────────────────────────
$terminal = Terminal::new();
$terminal->execute(
    Actions::alternateScreenEnable(),
    Actions::cursorHide(),
    Actions::setTitle('Color & Style Reference'),
);
$terminal->enableRawMode();

try {
    $size  = $terminal->info(Size::class);
    $cols  = $size ? $size->cols  : 80;
    $lines = $size ? $size->lines : 24;

    drawShowcase($terminal, $cols, $lines);

    while (true) {
        while ($event = $terminal->events()->next()) {
            return; // any key exits
        }
        usleep(10_000);
    }
} finally {
    $terminal->execute(
        Actions::cursorShow(),
        Actions::alternateScreenDisable(),
    );
    $terminal->disableRawMode();
}

// ── The rendering function — works with any Terminal backend ─────────────────
function drawShowcase(Terminal $terminal, int $cols, int $lines): void
{
    $terminal->queue(Actions::moveCursor(1, 1), Actions::clear(ClearType::All));

    $row = 1;

    // Header
    $header = ' php-tui/term — Color & Style Reference ';
    $terminal->queue(
        Actions::moveCursor($row, 1),
        Actions::setRgbBackgroundColor(20, 50, 100),
        Actions::setRgbForegroundColor(200, 220, 255),
        Actions::bold(true),
        Actions::printString(str_pad($header, $cols)),
        Actions::reset(),
    );
    $row += 2;

    // ── Text Styles ───────────────────────────────────────────────────────────
    if ($row <= $lines) {
        $terminal->queue(
            Actions::moveCursor($row, 1),
            Actions::setRgbForegroundColor(200, 180, 80),
            Actions::bold(true),
            Actions::printString('TEXT STYLES'),
            Actions::reset(),
        );
        $row++;
    }

    $styles = [
        ['Normal',        []],
        ['Bold',          [Actions::bold(true)]],
        ['Dim',           [Actions::dim(true)]],
        ['Italic',        [Actions::italic(true)]],
        ['Underline',     [Actions::underline(true)]],
        ['Slow Blink',    [Actions::slowBlink(true)]],
        ['Reverse',       [Actions::reverse(true)]],
        ['Strike',        [Actions::strike(true)]],
        ['Bold+Italic',   [Actions::bold(true), Actions::italic(true)]],
        ['Bold+Underline',[Actions::bold(true), Actions::underline(true)]],
    ];

    $col = 1;
    $colWidth = 16;
    foreach ($styles as $i => [$name, $actions]) {
        if ($row > $lines - 2) {
            break;
        }
        $terminal->queue(Actions::moveCursor($row, $col));
        foreach ($actions as $a) {
            $terminal->queue($a);
        }
        $terminal->queue(
            Actions::printString(str_pad($name, $colWidth - 1)),
            Actions::reset(),
        );
        $col += $colWidth;
        if ($col + $colWidth > $cols || ($i + 1) % 5 === 0) {
            $col = 1;
            $row++;
        }
    }
    $row++;

    // ── ANSI Foreground Colors ────────────────────────────────────────────────
    if ($row <= $lines - 2) {
        $terminal->queue(
            Actions::moveCursor($row, 1),
            Actions::setRgbForegroundColor(200, 180, 80),
            Actions::bold(true),
            Actions::printString('ANSI FOREGROUND'),
            Actions::reset(),
        );
        $row++;

        $ansiColors = [
            Colors::Black, Colors::Red, Colors::Green, Colors::Yellow,
            Colors::Blue, Colors::Magenta, Colors::Cyan, Colors::Gray,
            Colors::DarkGray, Colors::LightRed, Colors::LightGreen, Colors::LightYellow,
            Colors::LightBlue, Colors::LightMagenta, Colors::LightCyan, Colors::White,
        ];

        $col = 1;
        $fw = 14;
        foreach ($ansiColors as $i => $color) {
            if ($row > $lines - 2) {
                break;
            }
            $terminal->queue(
                Actions::moveCursor($row, $col),
                Actions::setForegroundColor($color),
                Actions::printString(str_pad($color->name, $fw - 1)),
                Actions::reset(),
            );
            $col += $fw;
            if ($col + $fw > $cols || ($i + 1) % 8 === 0) {
                $col = 1;
                $row++;
            }
        }
        $row++;
    }

    // ── ANSI Background Colors ────────────────────────────────────────────────
    if ($row <= $lines - 2) {
        $terminal->queue(
            Actions::moveCursor($row, 1),
            Actions::setRgbForegroundColor(200, 180, 80),
            Actions::bold(true),
            Actions::printString('ANSI BACKGROUND'),
            Actions::reset(),
        );
        $row++;

        $darkColors  = [Colors::Black, Colors::Blue, Colors::Red, Colors::Magenta, Colors::DarkGray];
        $col = 1;
        $fw  = 14;
        foreach ($ansiColors as $i => $color) {
            if ($row > $lines - 2) {
                break;
            }
            $fg = in_array($color, $darkColors, true) ? Colors::White : Colors::Black;
            $terminal->queue(
                Actions::moveCursor($row, $col),
                Actions::setBackgroundColor($color),
                Actions::setForegroundColor($fg),
                Actions::printString(str_pad($color->name, $fw - 1)),
                Actions::reset(),
            );
            $col += $fw;
            if ($col + $fw > $cols || ($i + 1) % 8 === 0) {
                $col = 1;
                $row++;
            }
        }
        $row++;
    }

    // ── RGB Gradient ──────────────────────────────────────────────────────────
    if ($row <= $lines - 2) {
        $terminal->queue(
            Actions::moveCursor($row, 1),
            Actions::setRgbForegroundColor(200, 180, 80),
            Actions::bold(true),
            Actions::printString('RGB GRADIENT'),
            Actions::reset(),
        );
        $row++;

        $barWidth = min($cols, 80);
        for ($c = 0; $c < $barWidth && $row <= $lines - 1; $c++) {
            [$r, $g, $b] = hsvToRgb($c / $barWidth, 1.0, 1.0);
            $terminal->queue(
                Actions::moveCursor($row, $c + 1),
                Actions::setRgbBackgroundColor($r, $g, $b),
                Actions::setRgbForegroundColor(
                    max(0, $r - 60),
                    max(0, $g - 60),
                    max(0, $b - 60)
                ),
                Actions::printString('█'),
                Actions::reset(),
            );
        }
        $row++;
    }

    // ── Box Drawing ───────────────────────────────────────────────────────────
    if ($row <= $lines - 4) {
        $terminal->queue(
            Actions::moveCursor($row, 1),
            Actions::setRgbForegroundColor(200, 180, 80),
            Actions::bold(true),
            Actions::printString('BOX DRAWING'),
            Actions::reset(),
        );
        $row++;

        $box = '┌───────────┐  ╔═══════════╗  ┏━━━━━━━━━━━┓';
        $m1  = '│ single    │  ║  double   ║  ┃   heavy   ┃';
        $bot = '└───────────┘  ╚═══════════╝  ┗━━━━━━━━━━━┛';
        foreach ([$box, $m1, $m1, $bot] as $line) {
            if ($row > $lines - 1) {
                break;
            }
            $terminal->queue(
                Actions::moveCursor($row, 1),
                Actions::setRgbForegroundColor(100, 160, 255),
                Actions::printString($line),
                Actions::reset(),
            );
            $row++;
        }
    }

    // ── Footer ───────────────────────────────────────────────────────────────
    $terminal->queue(
        Actions::moveCursor($lines, 1),
        Actions::setRgbBackgroundColor(20, 20, 20),
        Actions::setRgbForegroundColor(100, 100, 100),
        Actions::printString(str_pad(' Press any key to exit ', $cols)),
        Actions::reset(),
    );

    $terminal->flush();
}

/** @return array{int, int, int} */
function hsvToRgb(float $h, float $s, float $v): array
{
    $i = (int) ($h * 6);
    $f = $h * 6 - $i;
    $p = (int) (255 * $v * (1 - $s));
    $q = (int) (255 * $v * (1 - $f * $s));
    $t = (int) (255 * $v * (1 - (1 - $f) * $s));
    $V = (int) (255 * $v);

    return match ($i % 6) {
        0 => [$V, $t, $p],
        1 => [$q, $V, $p],
        2 => [$p, $V, $t],
        3 => [$p, $q, $V],
        4 => [$t, $p, $V],
        default => [$V, $p, $q],
    };
}

// ═════════════════════════════════════════════════════════════════════════════
// TESTABILITY PATTERN
// The code below is not executed — it demonstrates how to use stubs to test
// TUI rendering functions without a real terminal.
// ═════════════════════════════════════════════════════════════════════════════

if (false) {
    // Create a fake terminal with stub dependencies
    $painter  = ArrayPainter::new();
    $rawMode  = new TestRawMode();

    // Inject a fixed terminal size so tests are deterministic
    $infoProvider = ClosureInformationProvider::new(
        function (string $classFqn): ?TerminalInformation {
            if ($classFqn === Size::class) {
                return new Size(24, 80);
            }
            return null;
        }
    );

    // Inject scripted events (what the "user" will type)
    $events = ArrayEventProvider::fromEvents(
        CharKeyEvent::new('x'),              // some interaction
        CodedKeyEvent::new(KeyCode::Esc),    // then exit
    );

    $fakeTerm = Terminal::new(
        painter: $painter,
        infoProvider: $infoProvider,
        rawMode: $rawMode,
        eventProvider: $events,
    );

    // Call the same rendering function
    drawShowcase($fakeTerm, 80, 24);

    // Assert on captured actions — no real terminal required
    $actions = $painter->actions();
    assert(count($actions) > 0, 'Expected actions to be generated');

    $strings = array_map(fn (Action $a): string => $a->__toString(), $actions);
    assert(in_array('Print("php-tui/term — Color & Style Reference ")', $strings, true));

    echo "All assertions passed — rendering is testable without a real terminal.\n";
}
