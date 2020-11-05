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
     * @var
     */
    private $handle;

    /**
     * MoFileReader constructor.
     * @param $handle
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * {@inheritDoc}
     * @throws InvalidMoFileException
     */
    public function loadTranslations(?string $context = null): array
    {
        rewind($this->handle);

        $this->parseMagicNumber();
        $this->parseRevisionNumber();

        /** @var int $translationsCount Amount of strings */
        $translationsCount = $this->readInteger();
        /** @var int $sourceOffset Offset of table with original strings */
        $sourceOffset = $this->readInteger();
        /** @var int $translationOffset Offset of table with translated strings */
        $translationOffset = $this->readInteger();

        list($sourceLengths, $sourceOffsets) = $this->readOffsets($translationsCount, $sourceOffset);
        list($translationLengths, $translationOffsets) = $this->readOffsets($translationsCount, $translationOffset);

        return $this->readTranslations(
            $context,
            $translationsCount,
            $sourceLengths,
            $sourceOffsets,
            $translationLengths,
            $translationOffsets
        );
    }

    /**
     * Parse magic in MO file.
     *
     * @throws InvalidMoFileException
     */
    private function parseMagicNumber(): void
    {
        $unpackedData = unpack('c', $this->readByte(4));
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
     *
     * @throws InvalidMoFileException
     */
    private function parseRevisionNumber(): void
    {
        $revision = $this->readInteger();
        if ($revision != 0) {
            throw new InvalidMoFileException("revision number is invalid: $revision");
        }
    }

    /**
     * Read lengths and offsets from table.
     *
     * @param int $translationCount
     * @param int $offset Position where to look at
     *
     * @return array Lengths, Offsets
     */
    private function readOffsets(int $translationCount, int $offset): array
    {
        $lengths = $offsets = [];

        fseek($this->handle, $offset);

        for($index = 0; $index < $translationCount; ++$index) {
            $lengths[] = $this->readInteger();
            $offsets[] = $this->readInteger();
        }

        return [$lengths, $offsets];
    }

    /**
     * Build resulting array of source strings as keys and translated strings as values.
     *
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
                ? $this->readString($sourceLengths[$translationIndex], $sourceOffsets[$translationIndex])
                : null;
            $eotPos = $id ? strpos($id, chr(4)) : false;

            $isContextString = $context && $eotPos !== false && substr($id, 0, $eotPos) === $context;
            if ($isContextString || (!$context && $eotPos === false)) {
                if ($eotPos !== false) {
                    $id = substr($id, $eotPos + 1);
                }

                $result[$id] = $this->readString(
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
     *
     * @return int
     */
    private function readInteger(): int
    {
        $array=unpack($this->useBigEndian ? 'N' : 'V', $this->readByte(4));
        return current($array);
    }

    /**
     * Read string of $length characters from binary string.
     *
     * @param int $length
     *
     * @param int|null $offset
     * @return string|null
     */
    private function readString(int $length, ?int $offset = null): ?string
    {
        if ($offset !== null) {
            fseek($this->handle, $offset);
        }

        return $length > 0 ? $this->readByte($length) : null;
    }

    /**
     * Read given $amount of bytes from the $handle resource.
     *
     * @param int $amount
     *
     * @return false|string
     */
    private function readByte(int $amount)
    {
        return fread($this->handle, $amount);
    }
}
