<?php

namespace App\Services\Fbr;

/** What FBR answered for one submission. */
final class FbrResult
{
    public function __construct(
        public readonly string $number,
        public readonly int $httpStatus,
        public readonly array $response,
    ) {
    }
}
