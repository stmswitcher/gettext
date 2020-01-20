<?php
declare(strict_types=1);

namespace stmswitcher\gettext\Contract;

/**
 * An interface for file readers.
 *
 * @author Denis Alexandrov <stm.switcher@gmail.com>
 * @date 14.01.2020
 */
interface FileReader
{
    /**
     * Read translations from a file for given context.
     *
     * @param string $fileName
     * @param string|null $context
     *
     * @return array Source message => translation
     */
    public function loadTranslations(string $fileName, ?string $context = null): array;
}
