<?php
declare(strict_types=1);

namespace stmswitcher\gettext\tests\FileReader;

use stmswitcher\gettext\tests\TestCase;
use stmswitcher\gettext\FileReader\MoFileReader;

/**
 * Tests for .mo files reader.
 *
 * @author Denis Alexandrov <stm.switcher@gmail.com>
 * @date 15.01.20
 */

class MoFileReaderTest extends TestCase
{
    /**
     * @throws \stmswitcher\gettext\Exception\InvalidMoFileException
     */
    public function testLoadTranslations()
    {
        $reader = new MoFileReader();
        $result = $reader->loadTranslations(__DIR__ . '/../resources/test.mo', 'urls');

        $this->assertSame([
            'Test url' => 'test-url',
        ], $result);
    }
}
