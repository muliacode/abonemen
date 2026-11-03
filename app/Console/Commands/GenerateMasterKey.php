<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('master-key:generate')]
#[Description('Print a new master key for ABONEMEN_MASTER_KEY')]
class GenerateMasterKey extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('base64:'.base64_encode(random_bytes(32)));

        return self::SUCCESS;
    }
}
