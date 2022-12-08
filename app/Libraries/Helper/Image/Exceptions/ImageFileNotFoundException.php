<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Image\Adapter\Exceptions;

use InvalidArgumentException;
use Throwable;

class ImageFileNotFoundException extends InvalidArgumentException
{
    public function __construct(
        ?string $file = null,
        $code = 0,
        Throwable $previous = null
    ) {
        $message = $file
            ? sprintf('File %s has not found or is not readable.', $file)
            : 'File has not found or is not readable';
        parent::__construct($message, $code, $previous);
    }
}
