// database/migrations/xxxx_xx_xx_create_tbl_voice_signals_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTblVoiceSignalsTable extends Migration
{
    public function up()
    {
        Schema::create('tbl_voice_signals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->enum('type', [
                'offer',
                'answer',
                'ice-candidate',
                'call_ended',
                'incoming_call',
                'walkie_talkie'  // ✅ AGREGADO
            ]);
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamps();  // ✅ created_at y updated_at

            $table->foreign('from_user_id')->references('id')->on('tbl_user');
            $table->foreign('to_user_id')->references('id')->on('tbl_user');

            // ✅ ÍNDICES PARA MEJOR RENDIMIENTO
            $table->index(['to_user_id', 'processed', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tbl_voice_signals');
    }
}
