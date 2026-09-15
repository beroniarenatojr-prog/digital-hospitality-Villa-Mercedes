<?php

namespace App\Console\Commands;

use App\Models\EmailOtpVerification;
use Illuminate\Console\Command;

class PruneEmailOtpVerifications extends Command
{
    protected $signature = 'otp:prune';

    protected $description = 'Delete expired or consumed registration OTP records';

    public function handle(): int
    {
        $deleted = EmailOtpVerification::query()
            ->where(function ($query): void {
                $query->where('expires_at', '<', now())
                    ->whereNull('consumed_at');
            })
            ->orWhere(function ($query): void {
                $query->whereNotNull('consumed_at')
                    ->where('consumed_at', '<', now()->subDay());
            })
            ->delete();

        $this->info("Pruned {$deleted} registration OTP record(s).");

        return self::SUCCESS;
    }
}
