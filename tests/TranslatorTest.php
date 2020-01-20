<?php
declare(strict_types=1);

namespace stmswitcher\gettext\tests;

use stmswitcher\gettext\Translator;

/**
 * Tests for the main translator class.
 *
 * @author Denis Alexandrov <stm.switcher@gmail.come>
 * @date 15.01.20
 */

class TranslatorTest extends TestCase
{
    /**
     * @var Translator
     */
    private $translator;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = new Translator('en_US', __DIR__ . '/resources');
    }

    public function testTranslateUrl()
    {
        $this->assertSame('test-url', $this->translator->__('Test url', 'main', 'urls'));
    }

    public function testTranslatedWithoutContext()
    {
        $this->assertSame('This is a test translation', $this->translator->__('Test translation', 'main'));
    }

    public function testTranslateAnotherLocale()
    {
        $this->assertSame('тестовая-ссылка', $this->translator->__('Test url', 'main', 'urls', 'ru_RU'));
    }

    public function testTranslateWithPlaceholders()
    {
        $this->assertSame('Welcome to test page', $this->translator->__(['greet {title}', '{title}' => 'test page']));
    }
}
