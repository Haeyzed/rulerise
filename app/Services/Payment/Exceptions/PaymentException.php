<?php

namespace App\Services\Payment\Exceptions;

use Exception;

/**
 * Payment Exception
 * 
 * Custom exception for payment-related errors
 */
class PaymentException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
