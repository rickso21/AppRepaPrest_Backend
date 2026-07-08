<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestConnectionController extends Controller
{
    public function testConnection()
    {
        try {
            // Intentar conectar
            DB::connection()->getPdo();

            // Obtener versión de TiDB
            $version = DB::select('SELECT VERSION() as version');

            return response()->json([
                'success' => true,
                'message' => '✅ Conexión exitosa a TiDB Cloud!',
                'data' => [
                    'database' => DB::connection()->getDatabaseName(),
                    'version' => $version[0]->version ?? 'Unknown',
                    'host' => config('database.connections.mysql.host'),
                    'port' => config('database.connections.mysql.port'),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '❌ Error de conexión',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function testQuery()
    {
        try {
            // Crear tabla de prueba temporal
            DB::statement("
                CREATE TABLE IF NOT EXISTS test_connection (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    message VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Insertar registro
            DB::table('test_connection')->insert([
                'message' => 'Conexión exitosa desde Laravel!'
            ]);

            // Consultar registros
            $records = DB::table('test_connection')->get();

            return response()->json([
                'success' => true,
                'message' => '✅ Query ejecutado exitosamente!',
                'data' => $records
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '❌ Error en el query',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
