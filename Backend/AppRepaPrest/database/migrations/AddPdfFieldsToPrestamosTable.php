<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tbl_prestamo', function (Blueprint $table) {
            // Verificar si los campos ya existen
            if (!Schema::hasColumn('tbl_prestamo', 'ruta_pdf_aprobacion')) {
                $table->string('ruta_pdf_aprobacion')->nullable();
            }
            
            if (!Schema::hasColumn('tbl_prestamo', 'ruta_pdf_liquidacion')) {
                $table->string('ruta_pdf_liquidacion')->nullable();
            }
            
            if (!Schema::hasColumn('tbl_prestamo', 'ruta_pdf_rechazo')) {
                $table->string('ruta_pdf_rechazo')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_prestamo', function (Blueprint $table) {
            $table->dropColumn([
                'ruta_pdf_aprobacion',
                'ruta_pdf_liquidacion',
                'ruta_pdf_rechazo'
            ]);
        });
    }
};