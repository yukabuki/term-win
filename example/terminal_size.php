<?php

declare(strict_types=1);

use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\Terminal;
use PhpTui\Term\TerminalInformation\Size;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Live terminal size monitor.
 *
 * Resize your terminal window — the dimensions update in real-time.
 * A ruler along the edges helps you visualize the exact coordinates.
 *
 * Controls:
 *   q / ESC  →  quit
 */

$terminal = Terminal::new();
$terminal->execute(
    Actions::alternateScreenEnable(),
    Actions::cursorHide(),
    Actions::setTitle('Terminal Size Monitor'),
);
$terminal->enableRawMode();

$lastCols  = -1;
$lastLines = -1;

try {
    while (true) {
        // Poll terminal size — works on all platforms (no SIGWINCH required)
        $size  = $terminal->info(Size::class);
        $cols  = $size ? $size->cols  : 80;
        $lines = $size ? $size->lines : 24;

        if ($cols !== $lastCols || $lines !== $lastLines) {
            $lastCols  = $cols;
            $lastLines = $lines;
            render($terminal, $cols, $lines);
        }

        while ($event = $terminal->events()->next()) {
            if ($event instanceof CodedKeyEvent && $event->code === KeyCode::Esc) {
                return;
            }
            if ($event instanceof CharKeyEvent && ($event->char === 'q' || $event->char === 'Q')) {
                return;
            }
            if ($event instanceof CharKeyEvent && ord($event->char) === 0x03) { // Ctrl+C
                return;
            }
        }

        usleep(50_000); // 20 fps — enough for resize detection
    }
} finally {
    $terminal->execute(
        Actions::cursorShow(),
        Actions::alternateScreenDisable(),
    );
    $terminal->disableRawMode();
}

function render(Terminal $terminal, int $cols, int $lines): void
{
    $terminal->queue(Actions::moveCursor(1, 1), Actions::clear(ClearType::All));

    // ── Column ruler (row 1) ─────────────────────────────────────────────────
    $rulerLine = '';
    for ($c = 1; $c <= $cols; $c++) {
        if ($c === 1) {
            $rulerLine .= '1';
        } elseif ($c % 10 === 0) {
            $label      = (string) $c;
            $rulerLine  = substr($rulerLine, 0, strlen($rulerLine) - strlen($label) + 1) . $label;
        } elseif ($c % 5 === 0) {
            $rulerLine .= '·';
        } else {
            $rulerLine .= ' ';
        }
    }
    $terminal->queue(
        Actions::moveCursor(1, 1),
        Actions::setRgbForegroundColor(70, 70, 70),
        Actions::printString(substr($rulerLine, 0, $cols)),
        Actions::reset(),
    );

    // ── Row ruler (col 1, rows 2 to lines) ──────────────────────────────────
    for ($r = 2; $r <= $lines; $r++) {
        $terminal->queue(Actions::moveCursor($r, 1));
        $terminal->queue(Actions::setRgbForegroundColor(70, 70, 70));
        if ($r % 10 === 0) {
            $terminal->queue(Actions::printString(str_pad((string) $r, 4, ' ', STR_PAD_LEFT)));
        } elseif ($r % 5 === 0) {
            $terminal->queue(Actions::printString('   ·'));
        } else {
            $terminal->queue(Actions::printString('    '));
        }
        $terminal->queue(Actions::reset());
    }

    // ── Center panel ─────────────────────────────────────────────────────────
    $panelW = 40;
    $panelH = 11;
    $startRow = max(2, intdiv($lines - $panelH, 2) + 1);
    $startCol = max(5, intdiv($cols  - $panelW, 2) + 1);

    // Clamp so it fits
    if ($startRow + $panelH - 1 > $lines) {
        $startRow = max(2, $lines - $panelH + 1);
    }
    if ($startCol + $panelW - 1 > $cols) {
        $startCol = max(5, $cols - $panelW + 1);
    }

    $inner = $panelW - 2;
    drawBox($terminal, $startRow, $startCol, $panelW, $panelH, [60, 110, 200]);

    // Title
    printCenteredInBox($terminal, $startRow + 1, $startCol, $inner, 'Terminal Size', [100, 160, 255], true);

    // Separator
    $terminal->queue(
        Actions::moveCursor($startRow + 2, $startCol + 1),
        Actions::setRgbForegroundColor(50, 80, 140),
        Actions::printString(str_repeat('─', $inner)),
        Actions::reset(),
    );

    // Dimensions (large)
    $sizeStr = sprintf('%d × %d', $cols, $lines);
    printCenteredInBox($terminal, $startRow + 4, $startCol, $inner, $sizeStr, [220, 240, 255], true);
    printCenteredInBox($terminal, $startRow + 5, $startCol, $inner, 'columns × lines', [90, 110, 160], false);

    // Separator
    $terminal->queue(
        Actions::moveCursor($startRow + 7, $startCol + 1),
        Actions::setRgbForegroundColor(50, 80, 140),
        Actions::printString(str_repeat('─', $inner)),
        Actions::reset(),
    );

    // Hint
    printCenteredInBox($terminal, $startRow + 9, $startCol, $inner, 'q / ESC to exit', [60, 70, 90], false);

    // ── Footer ───────────────────────────────────────────────────────────────
    $footer = sprintf(' Resize the window to see it update live! │ %d×%d ', $cols, $lines);
    $terminal->queue(
        Actions::moveCursor($lines, 1),
        Actions::setRgbBackgroundColor(25, 25, 25),
        Actions::setRgbForegroundColor(100, 100, 100),
        Actions::printString(str_pad(mb_substr($footer, 0, $cols), $cols)),
        Actions::reset(),
    );

    $terminal->flush();
}

/**
 * @param int[] $rgb
 */
function drawBox(Terminal $terminal, int $row, int $col, int $w, int $h, array $rgb): void
{
    [$r, $g, $b] = $rgb;
    $inner = $w - 2;
    $top   = '┌' . str_repeat('─', $inner) . '┐';
    $mid   = '│' . str_repeat(' ', $inner) . '│';
    $bot   = '└' . str_repeat('─', $inner) . '┘';

    $terminal->queue(
        Actions::moveCursor($row, $col),
        Actions::setRgbForegroundColor($r, $g, $b),
        Actions::printString($top),
    );
    for ($i = 1; $i < $h - 1; $i++) {
        $terminal->queue(Actions::moveCursor($row + $i, $col), Actions::printString($mid));
    }
    $terminal->queue(
        Actions::moveCursor($row + $h - 1, $col),
        Actions::printString($bot),
        Actions::reset(),
    );
}

/**
 * @param int[] $rgb
 */
function printCenteredInBox(Terminal $terminal, int $row, int $boxCol, int $innerWidth, string $text, array $rgb, bool $bold): void
{
    $len  = mb_strlen($text);
    $pad  = max(0, intdiv($innerWidth - $len, 2));
    $line = str_repeat(' ', $pad) . $text;
    $line = str_pad($line, $innerWidth);

    $terminal->queue(
        Actions::moveCursor($row, $boxCol + 1),
        Actions::setRgbForegroundColor($rgb[0], $rgb[1], $rgb[2]),
    );
    if ($bold) {
        $terminal->queue(Actions::bold(true));
    }
    $terminal->queue(
        Actions::printString(substr($line, 0, $innerWidth)),
        Actions::reset(),
    );
}
