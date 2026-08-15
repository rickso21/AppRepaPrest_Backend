<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tbl_pagos', function (Blueprint $table) {
            // Verificar si las columnas ya existen antes de agregarlas
            if (!Schema::hasColumn('tbl_pagos', 'preference_id')) {
                $table->string('preference_id')->nullable()->after('status');
            }

            if (!Schema::hasColumn('tbl_pagos', 'no_pago_mp')) {
                $table->string('no_pago_mp')->nullable()->after('preference_id');
            }

            if (!Schema::hasColumn('tbl_pagos', 'status_mp')) {
                $table->string('status_mp')->nullable()->after('no_pago_mp');
            }

            if (!Schema::hasColumn('tbl_pagos', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('status_mp');
            }

            if (!Schema::hasColumn('tbl_pagos', 'fecha_verificacion')) {
                $table->timestamp('fecha_verificacion')->nullable()->after('payment_method');
            }

            if (!Schema::hasColumn('tbl_pagos', 'ruta_pdf')) {
                $table->string('ruta_pdf')->nullable()->after('fecha_verificacion');
            }

            if (!Schema::hasColumn('tbl_pagos', 'url_pdf')) {
                $table->string('url_pdf')->nullable()->after('ruta_pdf');
            }

            if (!Schema::hasColumn('tbl_pagos', 'usuario_confirmo')) {
                $table->unsignedBigInteger('usuario_confirmo')->nullable()->after('url_pdf');
                // Si quieres agregar una relación
                // $table->foreign('usuario_confirmo')->references('id')->on('tbl_user');
            }
        });
    }

    public function down()
    {
        Schema::table('tbl_pagos', function (Blueprint $table) {
            $table->dropColumn([
                'preference_id',
                'no_pago_mp',
                'status_mp',
                'payment_method',
                'fecha_verificacion',
                'ruta_pdf',
                'url_pdf',
                'usuario_confirmo'
            ]);
        });
    }
};
