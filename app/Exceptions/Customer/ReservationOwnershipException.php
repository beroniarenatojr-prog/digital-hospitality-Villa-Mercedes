<?php

namespace App\Exceptions\Customer;

use Exception;

class ReservationOwnershipException extends Exception
{
    public function render($request)
    {
        abort(403, $this->getMessage());
    }
}
