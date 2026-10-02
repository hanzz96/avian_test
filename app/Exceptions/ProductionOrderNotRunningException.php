<?php

namespace App\Exceptions;

class ProductionOrderNotRunningException extends CustomException
{
    public function __construct(string $woNumber, string $status)
    {
        parent::__construct("Production order {$woNumber} harus berstatus RUNNING (status saat ini: {$status}).");
    }
}
