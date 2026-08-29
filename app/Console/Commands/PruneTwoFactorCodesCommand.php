<?php

namespace App\Console\Commands;

use App\Models\TwoFactorCode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('two-factor:prune')]
#[Description('Delete expired email two-factor codes')]
class PruneTwoFactorCodesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $deleted = TwoFactorCode::query()->where('expires_at', '<', now())->delete();

        $this->info("Deleted {$deleted} expired two-factor code(s).");
    }
}
