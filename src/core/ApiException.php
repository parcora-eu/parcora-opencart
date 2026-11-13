<?php

namespace Parcora\OpenCart;

/**
 * Any failed Parcora API call: a transport error (status 0) or an error
 * envelope from the API, whose message is safe to show to the merchant.
 */
class ApiException extends \RuntimeException
{
    /** @var int */
    private $status;

    public function __construct(string $message, int $status = 0)
    {
        parent::__construct($message);

        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
