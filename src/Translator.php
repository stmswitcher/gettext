<?php
declare(strict_types=1);

namespace stmswitcher\gettext;

use stmswitcher\gettext\Exception\FileException;
use stmswitcher\gettext\Exception\GettextException;
use stmswitcher\gettext\FileReader\MoFileReader;

/**
 * Translator class for xgettext po / mo files.
 *
 * @author Denis Alexandrov <stm.switcher@gmail.com>
 * @date 14.01.2020
 */
class Translator
{
    public const DEFAULT_TRANSLATION_DOMAIN = 'main';

    /**
     * @var string
     */
    private $locale;

    /**
     * @var array|null Dictionary with translated messages
     */
    private $messages;

    /**
     * @var string Path to folder with messages
     */
    private $basePath;

    /**
     * @var bool If true - class will be throwing exception, if value is false and something goes wrong, untranslated
     * string will be returned.
     */
    private $debug = false;

    /**
     * @var bool If true - will return original entry, instead of the translation.
     * Can be set f.e. when translation file is not available.
     */
    private $useFallback = false;

    /**
     * @var MoFileReader
     */
    private $loader;

    /**
     * Translator constructor.
     *
     * @param string $locale
     * @param string $basePath
     * @param bool $debug
     */
    public function __construct(string $locale, string $basePath, bool $debug = false)
    {
        $this->locale = $locale;
        $this->basePath = $basePath;
        $this->debug = $debug;

        try {
            $filename = $this->getFileName($locale, self::DEFAULT_TRANSLATION_DOMAIN);
            $fileHandler = fopen($filename, 'rb');
            $extension = substr($filename, -2);
            switch ($extension) {
                case 'mo':
                    $loader = new MoFileReader($fileHandler);
                    break;
                default:
                    throw new FileException('Unsupported dictionary file exception');
            }
            $this->loader = $loader;
        } catch (FileException $exception) {
            $this->useFallback = true;
        }
    }

    /**
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string Get path to folder with messages
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param string|array $textToTranslate String of text to be translated or an array in case text has placeholders
     * @param string $domain Which translations domain to use
     * @param string|null $context Context of the text (ex. urls, cli etc.)
     *
     * @return string
     * @throws Exception\InvalidMoFileException
     * @throws FileException
     */
    public function __(
        $textToTranslate,
        string $domain = self::DEFAULT_TRANSLATION_DOMAIN,
        string $context = null
    ): string {
        $hasPlaceholders = is_array($textToTranslate);

        $string = $hasPlaceholders ? reset($textToTranslate) : $textToTranslate;

        try {
            if ($this->useFallback) {
                return $textToTranslate;
            }
            $translation = $this->loadMessages($this->locale, $domain, $context)[$string] ?? $string;
        } catch (GettextException $exception) {
            if ($this->debug) {
                throw $exception;
            }

            return $string;
        }

        if ($hasPlaceholders) {
            return $this->replacePlaceholders($translation, array_slice($textToTranslate, 1));
        }

        return $translation;
    }

    /**
     * Load messages for given $locale from given $catalog.
     *
     * @param string $locale
     * @param string $domain
     * @param string|null $context
     *
     * @return array
     * @throws Exception\InvalidMoFileException
     * @throws FileException
     */
    private function loadMessages(string $locale, string $domain, ?string $context = null): array
    {
        $key = join('.', array_filter([$locale, $domain ,$context]));
        if ($this->messages && isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        $this->messages[$key] = $this->loader->loadTranslations($context);

        return $this->messages[$key];
    }

    /**
     * Get suitable file with translations. Mo files are preferred.
     *
     * @param string $locale
     * @param string $domain
     *
     * @return string Absolute path to a file
     * @throws FileException
     */
    private function getFileName(string $locale, string $domain): string
    {
        $moFileName = $this->getBasePath() . "/$locale/$domain.mo";
        $poFileName = $this->getBasePath() . "/$locale/$domain.po";

        $moFileAvailable = is_file($moFileName) && is_readable($moFileName);
        $poFileAvailable = is_file($poFileName) && is_readable($poFileName);

        if (!$moFileAvailable && !$poFileAvailable) {
            throw new FileException("Can't access dictionary file $domain for locale $locale!");
        }

        return $moFileAvailable ? $moFileName : $poFileName;
    }

    /**
     * Replace placeholders in $text with $replacements from the array.
     *
     * @param string $text
     * @param array $replacements
     *
     * @return string
     */
    private function replacePlaceholders(string $text, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
