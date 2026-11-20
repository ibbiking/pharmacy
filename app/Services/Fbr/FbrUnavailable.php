<?php

namespace App\Services\Fbr;

use RuntimeException;

/** FBR could not be reached or failed on its side (timeout, 5xx). Retried in "Save and retry" mode. */
class FbrUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null, public readonly array $response = [])
    {
        parent::__construct($message);
    }
}
