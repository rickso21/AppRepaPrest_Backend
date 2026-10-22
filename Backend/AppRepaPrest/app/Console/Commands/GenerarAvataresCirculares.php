<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerarAvataresCirculares extends Command
{
    protected $signature = 'avatars:circulares';
    protected $description = 'Genera PNG circulares para todos los avatares existentes';

    public function handle()
    {
        $users = DB::select("
            SELECT id, nombre, avatar
            FROM tbl_user
            WHERE avatar IS NOT NULL
              AND avatar != ''
              AND avatar_circular IS NULL
        ");

        $this->info("Procesando " . count($users) . " avatares...");

        $ok = 0;
        $fail = 0;

        foreach ($users as $user) {
            $circular = user_generar_avatar_circular($user->avatar);

            if ($circular) {
                DB::statement("
                    UPDATE tbl_user
                    SET avatar_circular = ?
                    WHERE id = ?
                ", [$circular, $user->id]);

                $ok++;
                $this->line("{$user->id} - {$user->nombre}");
            } else {
                $fail++;
                $this->warn("{$user->id} - {$user->nombre}");
            }
        }

        $this->info("Listo: {$ok} OK, {$fail} fallidos");
    }
}
