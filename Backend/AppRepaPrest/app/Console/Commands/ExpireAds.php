<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:expire-ads')]
#[Description('Command description')]
class ExpireAds extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
{
    $expired = Ad::where('status_id', 1)
        ->whereNotNull('end_at')
        ->where('end_at', '<', now())
        ->update(['status_id' => 2]);   // 2 = expirado

    $this->info("Anuncios expirados: {$expired}");
    return 0;
}
}
