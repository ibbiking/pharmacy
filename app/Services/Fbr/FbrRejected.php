<?php

namespace App\Services\Fbr;

use RuntimeException;

/** FBR refused the submission (validation / 4xx / error code). Not retried. */
class FbrRejected extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null, public readonly array $response = [])
    {
        parent::__construct($message);
    }
}
