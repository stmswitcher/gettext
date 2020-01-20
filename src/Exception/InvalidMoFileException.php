<?php
declare(strict_types=1);

namespace stmswitcher\gettext\Exception;

/**
 * Exception for .mo files errors.
 *
 * @author Denis Alexandrov <stm.switcher@gmail.com>
 * @date 14.01.2020
 */
class InvalidMoFileException extends FileException
{
    private const MESSAGE_PREFIX = 'Invalid mo file: ';

    /**
     * @inheritDoc
     */
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->message = self::MESSAGE_PREFIX . $this->message;
    }
}
