<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $description, ?Model $subject = null, array $properties = []): void
    {
        $logger = activity()
            ->withProperties($properties);

        $user = Auth::user();
        if ($user !== null) {
            $logger->causedBy($user);
        }

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $logger->log($description);
    }
}
