<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\aprobar\aprobarRequest;
use App\Http\Requests\login\registerAdminRequest;
use App\Models\Prestamo;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Grupo;
use App\Models\LineaCredito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class adminController extends Controller
{

 // FUNCION PARA GENERAR USUARIO QUE APRUEBA PRESTAMOS
    public function index(registerAdminRequest $request)
    {
        if ($request->codigo != env('CODIGO_SEGURIDAD')) {
            return response()->json(['res' => false, 'msg' => 'No es posible generar el usuario'], 401);
        }
        $resp=['res' => false, 'msg' => 'No puede generarse el usuario sin telefono o email'];
        $status_resp = 400;
        if ($request->email == null && $request->telefono == null) {
            return response()->json($resp, $status_resp);
        }
        $user = new User();
        $user->nombre = $request->name;
        $user->apellido_p = $request->apellido_p;
        $user->apellido_m = $request->apellido_m;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->telefono = $request->telefono;
        $user->rol_id = 3;
        $user->status_id = 1;
        $resp['msg'] = "Se genero el usuario con Exito";
        $status_resp = 201;
        try {
            $user->save();
            $grupo = new Grupo();
            $grupo->code = "externo";
            $grupo->group_name = $request->name_group;
            $grupo->user_leader_id = $user->id;
            $grupo->status = 1;
            $grupo->save();
            $user->grupo_id = $grupo->id;
            $user->save();
        } catch (\Throwable $th) {
            $resp['msg'] = $th->getMessage();
            $status_resp = 409;
        }
        return response()->json($resp, $status_resp);
    }

 /**
     * ACTUALIZAR ESTADO DEL PRÉSTAMO CON GENERACIÓN AUTOMÁTICA DE PDF
     */
    public function aprobar_prestamo(aprobarRequest $request, int $id)
    {
        try {
            $user_token = $request->user();

            if ($user_token->rol_id != 3) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Usuario no autenticado'
                ], 401);
            }

            // Buscar préstamo activo

            /*
            $prestamo = Prestamo::where('id', $id)
                ->where('usuario_id', $request->usuario_id)
                ->whereNotIn('estado_prestamo_id', [3, 4])
                ->orderBy('id', 'desc')
                ->first();

                */


                    $prestamo = Prestamo::find($id);

            if (!$prestamo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene un préstamo activo para modificar'
                ], 404);
            }

            $estado_actual = $prestamo->estado_prestamo_id;
            $nuevo_estado = $request->estado_prestamo_id;

            // Validar transiciones
            if (!validarTransicionEstado($estado_actual, $nuevo_estado)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transición de estado no permitida',
                    'estado_actual' => $estado_actual,
                    'estado_actual_texto' => getEstadoTexto($estado_actual),
                    'nuevo_estado' => $nuevo_estado,
                    'nuevo_estado_texto' => getEstadoTexto($nuevo_estado),
                    'transiciones_permitidas' => getTransicionesPermitidas($estado_actual)
                ], 400);
            }

            if (esEstadoFinal($estado_actual)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede modificar un préstamo en estado final',
                    'estado_actual' => $estado_actual,
                    'estado_actual_texto' => getEstadoTexto($estado_actual)
                ], 400);
            }

            $linea_credito = LineaCredito::find($prestamo->linea_credito_id);
            $credito_reactivado = false;

            // Variable para controlar qué PDF generar
            $pdfGenerado = false;
            $tipoPdf = null;
            $rutaPdf = null;

            // ============================================
            // PROCESAR SEGÚN EL NUEVO ESTADO
            // ============================================
            switch ($nuevo_estado) {
                case 2: // Aprobado
                    // ============================================
                    // FECHAS DE APROBACIÓN Y DESEMBOLSO
                    // ============================================
                    $fecha_aprobacion = now();
                    $fecha_desembolso = $fecha_aprobacion->copy();

                    $prestamo->fecha_aprobacion = $fecha_aprobacion;
                    $prestamo->fecha_desembolso = $fecha_desembolso;
                    $prestamo->fecha_activacion = $fecha_desembolso;
                    $prestamo->fecha_primer_pago = $fecha_desembolso->copy()->addDays(15);
                    $prestamo->estado_prestamo_id = 2;

                    // Actualizar línea de crédito
                    if ($linea_credito) {
                        $linea_credito->estatus_id = 2; // En uso
                        $linea_credito->save();
                    }

                    // GENERAR PDF DE APROBACIÓN
                    $pdfData = $this->generarPdfPrestamo($prestamo, 'aprobacion', $user_token);
                    $pdfGenerado = true;
                    $tipoPdf = 'aprobacion';
                    $rutaPdf = $pdfData['ruta'] ?? null;
                    $folderPdf = $pdfData['folder'] ?? 'aprobacion';
                    break;

                    Log::info('Préstamo aprobado y PDF generado', [
                        'prestamo_id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $request->usuario_id,
                        'asesor_id' => $user_token->id,
                        'pdf_generado' => $pdfGenerado
                    ]);
                    break;

                case 3: // Pagado
                    // ============================================
                    // VALIDAR QUE EL PRÉSTAMO ESTÉ APROBADO
                    // ============================================
                    if ($prestamo->estado_prestamo_id != 2) {
                        return response()->json([
                            'success' => false,
                            'message' => 'El préstamo debe estar en estado Aprobado para marcarlo como Pagado',
                            'estado_actual' => $estado_actual,
                            'estado_actual_texto' => getEstadoTexto($estado_actual),
                            'accion' => 'Primero debe aprobar el préstamo'
                        ], 400);
                    }

                    // Verificar fecha de desembolso
                    if (!$prestamo->fecha_desembolso) {
                        return response()->json([
                            'success' => false,
                            'message' => 'El préstamo no tiene fecha de desembolso asignada',
                            'accion' => 'Primero debe aprobar el préstamo'
                        ], 400);
                    }

                    // Marcar como pagado
                    $prestamo->fecha_liquidacion = now();
                    $prestamo->estado_prestamo_id = 3;
                    $prestamo->incremento_aplicado = 0;
                    $prestamo->monto_restante = 0;

                    // Reactivar línea de crédito
                    if ($linea_credito) {
                        $linea_credito->estatus_id = 1; // Activa
                        $linea_credito->save();
                        $credito_reactivado = true;
                    }

                    // GENERAR PDF DE LIQUIDACIÓN
                    $pdfData = $this->generarPdfPrestamo($prestamo, 'liquidacion', $user_token);
                    $pdfGenerado = true;
                    $tipoPdf = 'liquidacion';
                    $rutaPdf = $pdfData['ruta'] ?? null;
                    $folderPdf = $pdfData['folder'] ?? 'liquidacion';

                    Log::info('Préstamo liquidado y PDF generado', [
                        'prestamo_id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $request->usuario_id,
                        'asesor_id' => $user_token->id,
                        'pdf_generado' => $pdfGenerado
                    ]);
                    break;

                case 4: // Rechazado
                    // Rechazar préstamo
                    $prestamo->estado_prestamo_id = 4;
                    $prestamo->motivo_rechazo = $request->motivo_rechazo ?? 'No especificado';

                    // Si estaba aprobado, limpiar fechas
                    if ($estado_actual == 2) {
                        $prestamo->fecha_aprobacion = null;
                        $prestamo->fecha_desembolso = null;
                        $prestamo->fecha_activacion = null;
                        $prestamo->fecha_primer_pago = null;
                    }

                    // Reactivar línea de crédito
                    if ($linea_credito) {
                        $linea_credito->estatus_id = 1; // Activa
                        $linea_credito->save();
                        $credito_reactivado = true;
                    }

                    //  GENERAR PDF DE RECHAZO
                   $pdfData = $this->generarPdfPrestamo($prestamo, 'rechazo', $user_token);
                    $pdfGenerado = true;
                    $tipoPdf = 'rechazo';
                    $rutaPdf = $pdfData['ruta'] ?? null;
                    $folderPdf = $pdfData['folder'] ?? 'rechazo';
                    break;

                    Log::info('Préstamo rechazado y PDF generado', [
                        'prestamo_id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $request->usuario_id,
                        'asesor_id' => $user_token->id,
                        'motivo' => $request->motivo_rechazo ?? 'No especificado',
                        'pdf_generado' => $pdfGenerado
                    ]);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Estado no válido',
                        'estado_recibido' => $nuevo_estado,
                        'estados_permitidos' => [2, 3, 4]
                    ], 400);
            }

            // ============================================
            // GUARDAR CAMBIOS
            // ============================================
            $prestamo->save();

            // ============================================
            // PREPARAR RESPUESTA
            // ============================================
            return response()->json([
                'success' => true,
                'message' => $nuevo_estado == 3 ? 'Préstamo marcado como pagado y línea de crédito reactivada' : 'Estado del préstamo actualizado correctamente',
                'data' => [
                    'prestamo' => [
                        'id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $prestamo->usuario_id,
                        'monto_solicitado' => (float) $prestamo->monto_solicitado,
                        'monto_total' => (float) $prestamo->monto_total_pagar,
                        'monto_restante' => (float) $prestamo->monto_restante,
                        'numero_pagos' => $prestamo->numero_pagos,
                        'pagos_realizados' => $prestamo->pagos_realizados ?? 0,
                    ],
                    'estado' => [
                        'anterior' => $estado_actual,
                        'anterior_texto' => getEstadoTexto($estado_actual),
                        'nuevo' => $nuevo_estado,
                        'nuevo_texto' => getEstadoTexto($nuevo_estado)
                    ],
                    'fechas' => [
                        'fecha_aprobacion' => $prestamo->fecha_aprobacion instanceof \Carbon\Carbon
                            ? $prestamo->fecha_aprobacion->format('Y-m-d H:i:s')
                            : $prestamo->fecha_aprobacion,
                        'fecha_desembolso' => $prestamo->fecha_desembolso instanceof \Carbon\Carbon
                            ? $prestamo->fecha_desembolso->format('Y-m-d')
                            : $prestamo->fecha_desembolso,
                        'fecha_activacion' => $prestamo->fecha_activacion instanceof \Carbon\Carbon
                            ? $prestamo->fecha_activacion->format('Y-m-d')
                            : $prestamo->fecha_activacion,
                        'fecha_primer_pago' => $prestamo->fecha_primer_pago instanceof \Carbon\Carbon
                            ? $prestamo->fecha_primer_pago->format('Y-m-d')
                            : $prestamo->fecha_primer_pago,
                        'fecha_liquidacion' => $prestamo->fecha_liquidacion instanceof \Carbon\Carbon
                            ? $prestamo->fecha_liquidacion->format('Y-m-d H:i:s')
                            : $prestamo->fecha_liquidacion,
                    ],
                    'linea_credito' => $linea_credito ? [
                        'id' => $linea_credito->id,
                        'limite_aprobado' => (int) $linea_credito->limite_aprobado,
                        'limite_disponible' => (int) $linea_credito->limite_disponible,
                        'estatus_id' => $linea_credito->estatus_id,
                        'estatus_texto' => $linea_credito->estatus_id == 1 ? 'Activa (Disponible)' : 'En Uso',
                        'credito_reactivado' => $credito_reactivado
                    ] : null,
                    // INCLUIR INFORMACIÓN DEL PDF GENERADO
                   'pdf' => $pdfGenerado ? [
                    'generado' => true,
                    'tipo' => $tipoPdf,
                    'carpeta' => $folderPdf ?? null,
                    'ruta' => $rutaPdf,
                    'url_descarga' => $rutaPdf ? asset("storage/prestamos/{$folderPdf}/" . basename($rutaPdf)) : null
                ] : [
                    'generado' => false
                ],
                    'registrado_por' => 'asesor',
                    'asesor_id' => $user_token->id,
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en actualizarEstadoPrestamo (Asesor)', [
                'usuario_id' => $request->usuario_id ?? null,
                'asesor_id' => $user_token->id ?? null,
                'nuevo_estado' => $request->estado_prestamo_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
            'message' => 'Error al actualizar el estado',
            'error' => $e->getMessage(), // Esto mostrará el error real
            'trace' => $e->getTraceAsString() // Esto mostrará la traza
            ], 500);
        }
    }


 /**
 * GENERAR PDF DEL PRÉSTAMO
 */
public function generarPdfPrestamo($prestamo, $tipo, $usuario)
{
    try {
        // Cargar relaciones
        $prestamo->load(['usuario', 'lineaCredito']);

        // Preparar datos para la vista
        $data = [
            'prestamo' => $prestamo,
            'usuario' => $prestamo->usuario,
            'linea_credito' => $prestamo->lineaCredito,
            'fecha_generacion' => now()->format('d/m/Y H:i:s'),
            'estado_texto' => getEstadoTexto($prestamo->estado_prestamo_id),
            'tipo_documento' => $tipo,
            'motivo_rechazo' => $prestamo->motivo_rechazo ?? null,
            'asesor' => $usuario
        ];

        // Determinar qué vista usar según el tipo
        $vista = match($tipo) {
            'aprobacion' => 'pdf.prestamo-aprobacion',
            'liquidacion' => 'pdf.prestamo-liquidacion',
            'rechazo' => 'pdf.prestamo-rechazo',
            default => 'pdf.prestamo-general'
        };

        $pdf = Pdf::loadView($vista, $data);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false
        ]);

        // RUTA CON CARPETA POR TIPO
        $filename = "prestamo_{$prestamo->id}_{$tipo}_" . now()->format('Ymd_His') . ".pdf";

        // Construir ruta con subcarpeta según el tipo
        $folder = match($tipo) {
            'aprobacion' => 'aprobacion',
            'liquidacion' => 'liquidacion',
            'rechazo' => 'rechazo',
            default => 'otros'
        };

        // Ruta relativa para la BD
        $dbPath = "public/prestamos/{$folder}/" . $filename;

        // Ruta absoluta para el sistema de archivos
        $basePath = storage_path('app/public/prestamos/' . $folder);
        $absolutePath = $basePath . DIRECTORY_SEPARATOR . $filename;

        Log::info('Ruta absoluta', [
            'path' => $absolutePath,
            'folder' => $folder,
            'tipo' => $tipo
        ]);

        // CREAR DIRECTORIO CON FILE SYSTEM (incluyendo subcarpeta)
        if (!is_dir($basePath)) {
            Log::info('Creando directorio: ' . $basePath);
            if (!mkdir($basePath, 0777, true)) {
                throw new \Exception("No se pudo crear el directorio: {$basePath}");
            }
        }

        // VERIFICAR PERMISOS DE ESCRITURA
        if (!is_writable($basePath)) {
            throw new \Exception("El directorio no tiene permisos de escritura: {$basePath}");
        }

        // GUARDAR EL PDF
        $pdfContent = $pdf->output();
        $bytesWritten = file_put_contents($absolutePath, $pdfContent);

        if ($bytesWritten === false || $bytesWritten === 0) {
            throw new \Exception("No se pudo escribir el PDF. Bytes escritos: {$bytesWritten}");
        }

        // VERIFICAR QUE EL ARCHIVO EXISTE
        if (!file_exists($absolutePath)) {
            throw new \Exception("El archivo no existe después de guardarlo: {$absolutePath}");
        }

        // Guardar la ruta en la base de datos
        $campo = "ruta_pdf_{$tipo}";
        $prestamo->$campo = $dbPath;
        $prestamo->save();

        Log::info('PDF generado exitosamente', [
            'prestamo_id' => $prestamo->id,
            'absolute_path' => $absolutePath,
            'db_path' => $dbPath,
            'bytes' => $bytesWritten,
            'folder' => $folder
        ]);

        return [
            'success' => true,
            'ruta' => $dbPath,
            'nombre' => $filename,
            'folder' => $folder,
            'url' => asset("storage/prestamos/{$folder}/" . $filename)
        ];

    } catch (\Exception $e) {
        Log::error('ERROR GENERANDO PDF', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}


/**
 * DESCARGAR PDF ESPECÍFICO
 */
public function descargarPdfPrestamo($prestamo_id, $tipo)
{
    try {
        $prestamo = Prestamo::findOrFail($prestamo_id);

        // Construir la ruta según el tipo
        $campo = "ruta_pdf_{$tipo}";
        if (!isset($prestamo->$campo) || !Storage::exists($prestamo->$campo)) {
            return response()->json([
                'success' => false,
                'message' => "No existe PDF de {$tipo} para este préstamo"
            ], 404);
        }

        // Obtener el nombre del archivo desde la ruta
        $fullPath = $prestamo->$campo;
        $filename = basename($fullPath);

        // Determinar la carpeta según el tipo
        $folder = match($tipo) {
            'aprobacion' => 'aprobacion',
            'liquidacion' => 'liquidacion',
            'rechazo' => 'rechazo',
            default => 'otros'
        };

        // Descargar el archivo
        return Storage::download(
            $fullPath,
            "prestamo_{$prestamo->folio}_{$tipo}.pdf"
        );

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al descargar el PDF',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /*

    public function listarPrestamos(Request $request)
    {
        try {
            $user_token = $request->user();

            if (!$user_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $estado = $request->estado;
            $usuario_id = $request->usuario_id;
            $folio = $request->folio;

            $query = Prestamo::with(['usuario', 'lineaCredito']);

            if ($estado) {
                $query->where('estado_prestamo_id', $estado);
            }

            if ($usuario_id) {
                $query->where('usuario_id', $usuario_id);
            }

            if ($folio) {
                $query->where('folio', 'LIKE', "%{$folio}%");
            }

            $prestamos = $query->orderBy('id', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => [
                    'prestamos' => $prestamos->map(function($prestamo) {
                        return [
                            'id' => $prestamo->id,
                            'folio' => $prestamo->folio,
                            'usuario_id' => $prestamo->usuario_id,
                            'usuario_nombre' => $prestamo->usuario->name ?? null,
                            'monto_solicitado' => $prestamo->monto_solicitado,
                            'monto_total' => $prestamo->monto_total_pagar,
                            'monto_restante' => $prestamo->monto_restante,
                            'estado' => getEstadoTexto($prestamo->estado_prestamo_id),
                            'estado_id' => $prestamo->estado_prestamo_id,
                            'fecha_solicitud' => $prestamo->fecha_solicitud,
                            'fecha_desembolso' => $prestamo->fecha_desembolso,
                            'pagos_realizados' => $prestamo->pagos_realizados ?? 0,
                            'total_pagos' => $prestamo->numero_pagos,
                            'porcentaje_pagado' => calcularPorcentajePagado($prestamo)
                        ];
                    }),
                    'pagination' => [
                        'current_page' => $prestamos->currentPage(),
                        'total' => $prestamos->total(),
                        'per_page' => $prestamos->perPage(),
                        'last_page' => $prestamos->lastPage()
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en listarPrestamos (Asesor)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al listar los préstamos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
*/

      /**
     * CONSULTAR ESTADO DE CUENTA DEL PRÉSTAMO
     */
    public function consultarEstadoCuenta(Request $request)
    {
        try {
            $user_token = $request->user();

            if ($user_token->rol_id != 3) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Usuario no autenticado'
                ], 401);
            }

            $prestamo_id = $request->prestamo_id;
            $usuario_id = $request->usuario_id;

            $query = Prestamo::with(['pagos' => function($query) {
                $query->orderBy('fecha_pago', 'desc');
            }]);

            if ($prestamo_id) {
                $query->where('id', $prestamo_id);
            } elseif ($usuario_id) {
                $query->where('usuario_id', $usuario_id);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar prestamo_id o usuario_id'
                ], 400);
            }

            $prestamo = $query->first();

            if (!$prestamo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Préstamo no encontrado'
                ], 404);
            }

            $total_pagado = $prestamo->pagos->sum('monto_pagado');
            $deuda_actual = $prestamo->monto_restante ?? $prestamo->monto_total_pagar;

            return response()->json([
                'success' => true,
                'data' => [
                    'prestamo' => [
                        'id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $prestamo->usuario_id,
                        'monto_inicial' => $prestamo->monto_solicitado,
                        'monto_total' => $prestamo->monto_total_pagar,
                        'monto_pagado' => $total_pagado,
                        'monto_restante' => $deuda_actual,
                        'numero_pagos' => $prestamo->numero_pagos,
                        'pagos_realizados' => $prestamo->pagos_realizados ?? 0,
                        'pagos_pendientes' => $prestamo->numero_pagos - ($prestamo->pagos_realizados ?? 0),
                        'estado' => getEstadoTexto($prestamo->estado_prestamo_id),
                        'porcentaje_avance' => calcularPorcentajePagado($prestamo),
                        'fecha_solicitud' => $prestamo->fecha_solicitud,
                        'fecha_aprobacion' => $prestamo->fecha_aprobacion,
                        'fecha_desembolso' => $prestamo->fecha_desembolso,
                        'fecha_activacion' => $prestamo->fecha_activacion,
                        'fecha_liquidacion' => $prestamo->fecha_liquidacion,
                        'fecha_ultimo_pago' => $prestamo->fecha_ultimo_pago,
                        'linea_credito_id' => $prestamo->linea_credito_id
                    ],
                    'historial_pagos' => $prestamo->pagos->map(function($pago) {
                        return [
                            'id' => $pago->id,
                            'monto' => $pago->monto_pagado,
                            'tipo' => $pago->tipo_pago,
                            'es_adelantado' => $pago->es_adelantado ?? false,
                            'fecha' => $pago->fecha_pago,
                            'referencia' => $pago->referencia,
                            'observaciones' => $pago->observaciones,
                            'saldo_restante' => $pago->monto_restante,
                            'metodo_pago' => $pago->metodo_pago ?? 'efectivo',
                            'registrado_por' => $pago->usuario_registro
                        ];
                    })
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en consultarEstadoCuenta (Asesor)', [
                'usuario_id' => $user_token->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el estado de cuenta',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function ve_prestamos(Request $request)
    {
        $user_token = $request->user();

        if ($user_token->rol_id != 3) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado'
            ], 401);
        }

        $prestamos = Prestamo::whereIn('estado_prestamo_id', [1, 2, 3])->get(); // Aprobado o Rechazado
        $listado = [];
        foreach ($prestamos as $prestamo) {
            $usuario = $prestamo->usuario->nombre . ' ' . $prestamo->usuario->apellido_p . ' ' . $prestamo->usuario->apellido_m;
            $monto_solicitado = $prestamo->monto_solicitado;
            $folio = $prestamo->folio;
            $monto_total_pagar = $prestamo->monto_total_pagar;
            $numero_pagos = $prestamo->numero_pagos;
            $pagos_realizados = $prestamo->pagos_realizados;

            $listado= [
                'usuario' => $usuario,
                'monto_solicitado' => $monto_solicitado,
                'folio' => $folio,
                'monto_total_pagar' => $monto_total_pagar,
                'numero_pagos' => $numero_pagos,
                'pagos_realizados' => $pagos_realizados
            ];
            // var_dump($listado);
        }
        return response()->json($listado, 200);
    }



}
