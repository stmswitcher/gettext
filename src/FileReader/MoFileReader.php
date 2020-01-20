<?php
declare(strict_types=1);

namespace stmswitcher\gettext\FileReader;

use stmswitcher\gettext\Contract\FileReader;
use stmswitcher\gettext\Exception\InvalidMoFileException;

/**
 * File reader for .mo files.
 *
 * @author Denis Alexandrov <stm.switcher@gmail.com>
 * @date 14.01.2020
 */
class MoFileReader implements FileReader
{
    private const MAGIC_NO_BIG_ENDIAN = -34;
    private const MAGIC_BIG_ENDIAN = -107;

    /**
     * @var bool
     */
    private $useBigEndian;

    /**
     * {@inheritDoc}
     * @throws InvalidMoFileException
     */
    public function loadTranslations(string $fileName, ?string $context = null): array
    {
        $handle = fopen($fileName, 'rb');

        $this->parseMagicNumber($handle);
        $this->parseRevisionNumber($handle);

        /** @var int $translationsCount Amount of strings */
        $translationsCount = $this->readInteger($handle);
        /** @var int $sourceOffset Offset of table with original strings */
        $sourceOffset = $this->readInteger($handle);
        /** @var int $translationOffset Offset of table with translated strings */
        $translationOffset = $this->readInteger($handle);

        list($sourceLengths, $sourceOffsets) = $this->readOffsets($handle, $translationsCount, $sourceOffset);
        list($translationLengths, $translationOffsets) = $this->readOffsets($handle, $translationsCount, $translationOffset);

        $translations = $this->readTranslations(
            $handle,
            $context,
            $translationsCount,
            $sourceLengths,
            $sourceOffsets,
            $translationLengths,
            $translationOffsets
        );

        fclose($handle);

        return $translations;
    }

    /**
     * Parse magic in MO file.
     *
     * @param resource $handle
     *
     * @throws InvalidMoFileException
     */
    private function parseMagicNumber($handle): void
    {
        $unpackedData = unpack('c', $this->readByte($handle, 4));
        $magicNumber = current($unpackedData);

        switch ($magicNumber) {
            case self::MAGIC_BIG_ENDIAN:
                $this->useBigEndian = true;
                break;
            case self::MAGIC_NO_BIG_ENDIAN:
                $this->useBigEndian = false;
                break;
            default:
                throw new InvalidMoFileException("unknown magic number: $magicNumber");
        }
    }

    /**
     * Check mo file revision.
     *
     * @param resource $handle
     *
     * @throws InvalidMoFileException
     */
    private function parseRevisionNumber($handle): void
    {
        $revision = $this->readInteger($handle);
        if ($revision != 0) {
            throw new InvalidMoFileException("revision number is invalid: $revision");
        }
    }

    /**
     * Read lengths and offsets from table.
     *
     * @param resource $handle
     * @param int $translationCount
     * @param int $offset Position where to look at
     *
     * @return array Lengths, Offsets
     */
    private function readOffsets($handle, int $translationCount, int $offset): array
    {
        $lengths = $offsets = [];

        fseek($handle, $offset);

        for($index = 0; $index < $translationCount; ++$index) {
            $lengths[] = $this->readInteger($handle);
            $offsets[] = $this->readInteger($handle);
        }

        return [$lengths, $offsets];
    }

    /**
     * Build resulting array of source strings as keys and translated strings as values.
     *
     * @param resource $handle
     * @param string|null $context
     * @param int $translationCount
     * @param array $sourceLengths
     * @param array $sourceOffsets
     * @param array $translationLengths
     * @param array $translationOffsets
     *
     * @return array
     */
    private function readTranslations(
        $handle,
        ?string $context,
        int $translationCount,
        array $sourceLengths,
        array $sourceOffsets,
        array $translationLengths,
        array $translationOffsets
    ): array {
        $result = [];

        for ($translationIndex = 0; $translationIndex < $translationCount; ++$translationIndex) {
            $id = $sourceOffsets[$translationIndex] > 0
                ? $this->readString($handle, $sourceLengths[$translationIndex], $sourceOffsets[$translationIndex])
                : null;
            $eotPos = $id ? strpos($id, chr(4)) : false;

            $isContextString = $context && $eotPos !== false && substr($id, 0, $eotPos) === $context;
            if ($isContextString || (!$context && $eotPos === false)) {
                if ($eotPos !== false) {
                    $id = substr($id, $eotPos + 1);
                }

                $result[$id] = $this->readString(
                    $handle,
                    $translationLengths[$translationIndex],
                    $translationOffsets[$translationIndex]
                );
            }
        }

        return $result;
    }

    /**
     * Read integer from binary string.
     *
     * @param resource $handle
     *
     * @return int
     */
    private function readInteger($handle): int
    {
        $array=unpack($this->useBigEndian ? 'N' : 'V', $this->readByte($handle, 4));
        return current($array);
    }

    /**
     * Read string of $length characters from binary string.
     *
     * @param resource $handle
     * @param int $length
     *
     * @return string|null
     */
    private function readString($handle, int $length, ?int $offset = null): ?string
    {
        if ($offset !== null) {
            fseek($handle, $offset);
        }

        return $length > 0 ? $this->readByte($handle, $length) : null;
    }

    /**
     * Read given $amount of bytes from the $handle resource.
     *
     * @param resource $handle
     * @param int $amount
     *
     * @return false|string
     */
    private function readByte($handle, int $amount)
    {
        return fread($handle, $amount);
    }
}
