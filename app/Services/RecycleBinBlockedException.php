<?php

namespace App\Services;

use RuntimeException;

class RecycleBinBlockedException extends RuntimeException
{
    /**
     * @param array<int, array{type:string, count:int, action:string}> $references
     */
    public function __construct(string $message, public readonly array $references)
    {
        parent::__construct($message);
    }
}
