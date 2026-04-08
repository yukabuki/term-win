<?php

declare(strict_types=1);

namespace PhpTui\Term\Tests\InformationProvider;

use PhpTui\Term\InformationProvider\SizeFromWinProvider;
use PhpTui\Term\TerminalInformation\Size;
use PhpTui\Term\Tests\Fixtures\FakeWindowsConsole;
use PHPUnit\Framework\TestCase;

final class SizeFromWinProviderTest extends TestCase
{
    public function testReturnsNullForUnknownClass(): void
    {
        $console = new FakeWindowsConsole();
        $provider = SizeFromWinProvider::new($console);

        /**
         * @phpstan-ignore-next-line */
        self::assertNull($provider->for(\stdClass::class));
    }

    public function testReturnsVisibleWindowSize(): void
    {
        $console = new FakeWindowsConsole();
        $console->setScreenBufferInfo([
            'screenBufferSize' => ['x' => 220, 'y' => 9000], // large scroll buffer
            'cursorPosition' => ['x' => 0, 'y' => 0],
            'windowSize' => ['width' => 120, 'height' => 40], // actual visible area
            'maximumWindowSize' => ['x' => 220, 'y' => 50],
            'attributes' => 7,
        ]);

        $provider = SizeFromWinProvider::new($console);
        $size = $provider->for(Size::class);

        self::assertInstanceOf(Size::class, $size);
        self::assertEquals(40, $size->lines);
        self::assertEquals(120, $size->cols);
    }

    public function testWindowSizeNotScreenBufferSize(): void
    {
        // Verifies we use windowSize (visible area), not screenBufferSize (scrollback buffer)
        $console = new FakeWindowsConsole();
        $console->setScreenBufferInfo([
            'screenBufferSize' => ['x' => 300, 'y' => 3000],
            'cursorPosition' => ['x' => 0, 'y' => 0],
            'windowSize' => ['width' => 80, 'height' => 24],
            'maximumWindowSize' => ['x' => 300, 'y' => 50],
            'attributes' => 7,
        ]);

        $provider = SizeFromWinProvider::new($console);
        /** @var Size $size */
        $size = $provider->for(Size::class);

        self::assertEquals(24, $size->lines);
        self::assertEquals(80, $size->cols);
    }

    public function testZeroSizeIsAllowed(): void
    {
        $console = new FakeWindowsConsole();
        $console->setScreenBufferInfo([
            'screenBufferSize' => ['x' => 0, 'y' => 0],
            'cursorPosition' => ['x' => 0, 'y' => 0],
            'windowSize' => ['width' => 0, 'height' => 0],
            'maximumWindowSize' => ['x' => 0, 'y' => 0],
            'attributes' => 0,
        ]);

        $provider = SizeFromWinProvider::new($console);
        /** @var Size $size */
        $size = $provider->for(Size::class);

        self::assertEquals(0, $size->lines);
        self::assertEquals(0, $size->cols);
    }
}
