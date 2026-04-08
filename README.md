PHP Term
========

[![CI](https://github.com/php-tui/term/actions/workflows/ci.yml/badge.svg)](https://github.com/php-tui/term/actions/workflows/ci.yml)

<p align="center">
  <img src="https://github.com/php-tui/term/assets/530801/ca7a8e17-2238-496d-aaa8-83c92f2f5009" alt="Term Logo"/>
</p>

Low-level terminal control library **heavily** inspired by
[crossterm](https://github.com/crossterm-rs/crossterm).

Table of Contents
-----------------

- [Installation](#installation)
- [Requirements](#requirements)
- [Usage](#usage)
    - [Actions](#actions)
    - [Events](#events)
    - [Terminal Size](#terminal-size)
    - [Raw Mode](#raw-mode)
    - [ANSI parsing](#parsing)
- [Testing](#testing)
- [Windows Support](#windows-support)
- [Contributing](#contributing)

Installation
------------

```
$ composer require php-tui/term
```

Requirements
------------

- PHP 8.1+
- **Linux / macOS / WSL**: `stty` must be available (standard on all POSIX systems)
- **Windows**: PHP must be compiled with the [FFI extension](https://www.php.net/manual/en/book.ffi.php) enabled (`extension=ffi`, `ffi.enable=true` in `php.ini`)

Usage
-----

### Actions

You can send data to the terminal using _actions_.

```php
<?php

$terminal = Terminal::new();

// queue an action
$terminal->queue(Actions::printString('Hello World'));

// flush the queue to the terminal
$terminal->flush();

// or you can execute it directly
$terminal->execute(Actions::printString('Hello World'));
```

All actions are made available via. the `Actions` factory:

| method | description |
| --- | --- |
| `Actions::requestCursorPosition` | Request the cursor position.<br/><br/>This will (hopefully) be returned by the terminal and will be provided<br/>as an `PhpTui\Term\Event\CursorPositionEvent`. |
| `Actions::alternateScreenEnable` | Enable the alternate screen.<br/><br/>Allows switching back to the users previous "screen" later. |
| `Actions::alternateScreenDisable` | Disable the alternate screen |
| `Actions::printString` | Echo a standard string to the terminal |
| `Actions::cursorShow` | Show the cursor |
| `Actions::cursorHide` | Hide the cursor |
| `Actions::setRgbForegroundColor` | Set the foreground color using RGB |
| `Actions::setRgbBackgroundColor` | Set the background color using RGB |
| `Actions::setForegroundColor` | Set the foreground color to one of the ANSI base colors |
| `Actions::setBackgroundColor` | Set the background color to one of the ANSI base colors |
| `Actions::moveCursor` | Move the cursor to an absolute position.<br/><br/>The top left cell is line=1, col=1. |
| `Actions::reset` | Reset all modes (styles and colors) |
| `Actions::bold` | Enable or disable the bold styling |
| `Actions::dim` | Enable or disable the dim styling |
| `Actions::italic` | Enable or disable the italic styling |
| `Actions::underline` | Enable or disable the underline styling |
| `Actions::slowBlink` | Enable or disable the slow blink styling |
| `Actions::rapidBlink` | Enable or disable the rapid blink styling |
| `Actions::reverse` | Enable or disable the reverse blink styling |
| `Actions::hidden` | Enable or disable the hidden styling - useful for passwords. |
| `Actions::strike` | Enable or disable the strike-through styling |
| `Actions::clear` | Perform a clear operation.<br/><br/>The type of clear operation is given with the Enum for example<br/><br/>`Actions::clear(ClearType::All)`<br/><br/>Will clear the entire screen. |
| `Actions::enableMouseCapture` | Enable mouse capture.<br/><br/>Once this action has been issued mouse events will be made available. |
| `Actions::disableMouseCapture` | Disable mouse capture |
| `Actions::scrollUp` | Scroll the terminal up the given number of rows |
| `Actions::scrollDown` | Scroll the terminal down the given number of rows |
| `Actions::setTitle` | Set the title of the terminal for the current process. |
| `Actions::lineWrap` | Enable or disable line wrap |
| `Actions::moveCursorNextLine` | Move the cursor down and to the start of the next line (or the given number of lines) |
| `Actions::moveCursorPreviousLine` | Move the cursor up and to the start of the previous line (or the given number of lines) |
| `Actions::moveCursorToColumn` | Move the cursor to the given column (0-based) |
| `Actions::moveCursorToRow` | Move the cursor to the given row (0-based) |
| `Actions::moveCursorUp` | Move cursor up 1 or the given number of rows. |
| `Actions::moveCursorRight` | Move cursor right 1 or the given number of columns. |
| `Actions::moveCursorDown` | Move cursor down 1 or the given number of rows. |
| `Actions::moveCursorLeft` | Move cursor left 1 or the given number of columns. |
| `Actions::saveCursorPosition` | Save the cursor position |
| `Actions::restoreCursorPosition` | Restore the cursor position |
| `Actions::enableCusorBlinking` | Enable cursor blinking |
| `Actions::disableCursorBlinking` | Disable cursor blinking |
| `Actions::setCursorStyle` | Set the cursor style |

### Events

Term provides user events:

```php
while (true) {
    while ($event = $terminal->events()->next()) {
        if ($event instanceof CodedKeyEvent) {
            if ($event->code === KeyCode::Esc) {
                // escape pressed
            }
        }
        if ($event instanceof CharKeyEvent) {
            if ($event->char === 'c' && $event->modifiers === KeyModifiers::CONTROL) {
                // ctrl-c pressed
            }
        }
    }
    usleep(10000);
}
```

The events are as follows:

- `PhpTui\Term\Event\CharKeyEvent`: Standard character key (includes Unicode on all platforms)
- `PhpTui\Term\Event\CodedKeyEvent`: Special key, e.g. escape, control, page
  up, arrow down, etc
- `PhpTui\Term\Event\CursorPositionEvent`: as a response to
  `Actions::requestCursorPosition`.
- `PhpTui\Term\Event\FocusEvent`: for when focus has been gained or lost
- `PhpTui\Term\Event\FunctionKeyEvent`: when a function key is pressed
- `PhpTui\Term\Event\MouseEvent`: When the
  `Actions::enableMouseCapture` has been called, provides mouse event
  information.
- `PhpTui\Term\Event\TerminalResizedEvent`: The terminal was resized (Linux/macOS only via SIGWINCH; use size polling on Windows).

### Terminal Size

You can request the terminal size:

```php
<?php

$terminal = Terminal::new();

$size = $terminal->info(Size::class);
if (null !== $size) {
    echo $size->__toString() . "\n";
} else {
    echo 'Could not determine terminal size'."\n";
}
```

On Windows the visible window size is returned (not the scrollback buffer size).

### Raw Mode

Raw mode disables all the default terminal behaviors and is what you typically
want to enable when you want a fully interactive terminal.

```php
<?php

$terminal = Terminal::new();
$terminal->enableRawMode();
$terminal->disableRawMode();
```

Always be sure to disable raw mode — use a `try/finally` block to guarantee
cleanup even on exceptions:

```php
$terminal->enableRawMode();
try {
    // your event loop
} finally {
    $terminal->disableRawMode();
}
```

**Mouse input** is disabled in raw mode on all platforms. Call
`Actions::enableMouseCapture()` explicitly to receive `MouseEvent`s.

### Parsing

In addition Term provides a parser which can parse any escape code emitted by
the actions.

This is useful if you want to capture the output from a terminal application
and convert it to a set of Actions which can then be redrawn in another medium
(e.g. plain text or HTML).

```php
use PhpTui\Term\AnsiParser;
$actions = AnsiParser::parseString($rawAnsiOutput, true);
```

## Testing

The `Terminal` has testable versions of all its dependencies:

```php
<?php

$painter = ArrayPainter::new();
$eventProvider = ArrayEventProvider::fromEvents(
    CharKeyEvent::new('c')
);
$infoProvider = ClosureInformationProvider::new(
    function (string $classFqn): ?TerminalInformation {
        if ($classFqn === Size::class) {
            return new Size(24, 80);
        }
        return null;
    }
);
$rawMode = new TestRawMode();

$term = Terminal::new(
    painter: $painter,
    infoProvider: $infoProvider,
    eventProvider: $eventProvider,
    rawMode: $rawMode,
);
$term->execute(
    Actions::printString('Hello World'),
    Actions::setTitle('Terminal Title'),
);

echo implode("\n", array_map(
    fn (Action $action) => $action->__toString(),
    $painter->actions()
)). "\n";
```

See `example/testable.php` for a full demonstration.

## Windows Support

Native Windows is fully supported via PHP's [FFI extension](https://www.php.net/manual/en/book.ffi.php),
which calls the Windows Console API (`kernel32.dll`) directly. No external
tools (`stty`, etc.) are required.

### Setup

Enable FFI in your `php.ini`:

```ini
extension=ffi
ffi.enable=true
```

Verify with:

```
php -m | findstr ffi
```

### How it works

| Concern | Linux/macOS | Windows |
| --- | --- | --- |
| Raw mode | `stty raw -echo` | `SetConsoleMode` (kernel32) |
| Terminal size | `stty -a` | `GetConsoleScreenBufferInfo` (visible window) |
| Input reading | Non-blocking `STDIN` stream | `ReadConsoleInputW` (Unicode) |
| Mouse capture | ANSI VT sequences | `ENABLE_MOUSE_INPUT` flag + ANSI VT sequences |

### Examples

```
php example/events.php          # real-time event inspector (keyboard, mouse, focus)
php example/terminal_size.php   # live terminal size monitor with ruler
php example/testable.php        # color & style reference card
```

## Contributing

PRs for missing functionalities and improvements are welcome.
