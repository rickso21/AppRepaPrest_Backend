<?php

namespace App\Http\Controllers\MercadoPagoAllExterno;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LineaCredito;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MercadoPagoController extends Controller
{
/**
 * CONSULTAR INFORMACIÓN DE UN PAGO EN MERCADO PAGO
 * Esta función obtiene todos los datos que Mercado Pago guardó sobre un pago específico
 */
public function consultarPagoMercadoPago(Request $request)
{
    try {
        // Validar que se reciba un ID de pago o preference_id
        $request->validate([
            'pago_id' => 'nullable|exists:tbl_pagos,id',
            'preference_id' => 'nullable|string|max:100',
            'payment_id' => 'nullable|string|max:100', // ID de pago de MP
        ]);

        // Buscar el pago en nuestra base de datos
        $pago = null;
        $identificador_usado = '';

        if ($request->has('pago_id') && $request->pago_id) {
            $pago = Pago::find($request->pago_id);
            $identificador_usado = "pago_id: {$request->pago_id}";
        } elseif ($request->has('preference_id') && $request->preference_id) {
            $pago = Pago::where('preference_id', $request->preference_id)->first();
            $identificador_usado = "preference_id: {$request->preference_id}";
        } elseif ($request->has('payment_id') && $request->payment_id) {
            // Si tenemos el payment_id de MP, podemos buscar o consultar directamente
            $pago = Pago::where('no_pago_mp', $request->payment_id)->first();
            $identificador_usado = "payment_id: {$request->payment_id}";
        }

        if (!$pago) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el pago en la base de datos con el identificador proporcionado',
                'identificador_busqueda' => $identificador_usado,
            ], 404);
        }

        // Verificar si tenemos el no_pago_mp (ID del pago en Mercado Pago)
        if (empty($pago->no_pago_mp)) {
            return response()->json([
                'success' => false,
                'message' => 'El pago no tiene un ID de transacción en Mercado Pago asociado',
                'pago' => [
                    'id' => $pago->id,
                    'preference_id' => $pago->preference_id,
                    'estado' => $pago->estado_pago,
                    'status' => $pago->status,
                ],
            ], 400);
        }

        // Consultar la API de Mercado Pago
        $token = config('services.mercadopago.access_token');

        if (empty($token) || $token === 'null') {
            return response()->json([
                'success' => false,
                'message' => 'Token de Mercado Pago no configurado',
            ], 500);
        }

        // Realizar la consulta a la API de Mercado Pago
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$pago->no_pago_mp}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión con Mercado Pago: ' . $error,
            ], 500);
        }

        if ($httpCode !== 200) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el pago en Mercado Pago',
                'http_code' => $httpCode,
                'response' => json_decode($response, true),
            ], 500);
        }

        $datos_mercado_pago = json_decode($response, true);

        // Enriquecer la respuesta con información de nuestra base de datos
        return response()->json([
            'success' => true,
            'message' => 'Información obtenida exitosamente de Mercado Pago',
            'data' => [
                // Datos de nuestro sistema
                'nuestro_registro' => [
                    'id' => $pago->id,
                    'prestamo_id' => $pago->prestamo_id,
                    'usuario_id' => $pago->usuario_id,
                    'monto_pagado' => $pago->monto_pagado,
                    'monto_restante' => $pago->monto_restante,
                    'fecha_pago' => $pago->fecha_pago,
                    'estado_pago' => $pago->estado_pago,
                    'status' => $pago->status,
                    'preference_id' => $pago->preference_id,
                    'no_pago_mp' => $pago->no_pago_mp,
                    'metodo_pago' => $pago->metodo_pago,
                    'referencia' => $pago->referencia,
                    'observaciones' => $pago->observaciones,
                    'fecha_confirmacion' => $pago->fecha_confirmacion,
                    'fecha_verificacion' => $pago->fecha_verificacion,
                    'status_mp' => $pago->status_mp,
                    'payment_method' => $pago->payment_method,
                    'url_pdf' => $pago->url_pdf,
                ],

                // Datos completos de Mercado Pago
                'mercado_pago' => $this->extraerInformacionRelevanteMP($datos_mercado_pago),

                // Datos completos sin filtrar (para debugging)
                'mercado_pago_completo' => $datos_mercado_pago,
            ],
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al consultar información de Mercado Pago',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}

/**
 * EXTRAER INFORMACIÓN RELEVANTE DE LA RESPUESTA DE MERCADO PAGO
 * Organiza los datos más importantes para facilitar su visualización
 */
private function extraerInformacionRelevanteMP($datos)
{
    return [
        // Identificadores
        'id' => $datos['id'] ?? null,
        'external_reference' => $datos['external_reference'] ?? null,
        'preference_id' => $datos['preference_id'] ?? null,
        'merchant_order_id' => $datos['merchant_order_id'] ?? null,

        // Estado del pago
        'status' => $datos['status'] ?? null,
        'status_detail' => $datos['status_detail'] ?? null,

        // Montos
        'transaction_amount' => $datos['transaction_amount'] ?? null,
        'taxes_amount' => $datos['taxes_amount'] ?? null,
        'shipping_amount' => $datos['shipping_amount'] ?? null,
        'total_paid_amount' => $datos['total_paid_amount'] ?? null,
        'net_received_amount' => $datos['net_received_amount'] ?? null,
        'marketplace_fee' => $datos['marketplace_fee'] ?? null,
        'coupon_amount' => $datos['coupon_amount'] ?? null,

        // Fechas
        'date_created' => $datos['date_created'] ?? null,
        'date_approved' => $datos['date_approved'] ?? null,
        'date_last_updated' => $datos['date_last_updated'] ?? null,
        'date_of_expiration' => $datos['date_of_expiration'] ?? null,

        // Método de pago
        'payment_method_id' => $datos['payment_method_id'] ?? null,
        'payment_type_id' => $datos['payment_type_id'] ?? null,
        'issuer_id' => $datos['issuer_id'] ?? null,
        'installments' => $datos['installments'] ?? null,

        // Información del pagador
        'payer' => [
            'id' => $datos['payer']['id'] ?? null,
            'email' => $datos['payer']['email'] ?? null,
            'identification' => $datos['payer']['identification'] ?? null,
            'first_name' => $datos['payer']['first_name'] ?? null,
            'last_name' => $datos['payer']['last_name'] ?? null,
            'phone' => $datos['payer']['phone'] ?? null,
        ],

        // Tarjeta de crédito/débito (si aplica)
        'card' => [
            'id' => $datos['card']['id'] ?? null,
            'first_six_digits' => $datos['card']['first_six_digits'] ?? null,
            'last_four_digits' => $datos['card']['last_four_digits'] ?? null,
            'expiration_month' => $datos['card']['expiration_month'] ?? null,
            'expiration_year' => $datos['card']['expiration_year'] ?? null,
            'cardholder' => [
                'name' => $datos['card']['cardholder']['name'] ?? null,
                'identification' => $datos['card']['cardholder']['identification'] ?? null,
            ],
        ],

        // Detalles de la transacción
        'transaction_details' => [
            'net_received_amount' => $datos['transaction_details']['net_received_amount'] ?? null,
            'total_paid_amount' => $datos['transaction_details']['total_paid_amount'] ?? null,
            'overpaid_amount' => $datos['transaction_details']['overpaid_amount'] ?? null,
            'external_resource_url' => $datos['transaction_details']['external_resource_url'] ?? null,
            'installment_amount' => $datos['transaction_details']['installment_amount'] ?? null,
            'financial_institution' => $datos['transaction_details']['financial_institution'] ?? null,
            'payment_method_reference_id' => $datos['transaction_details']['payment_method_reference_id'] ?? null,
        ],

        // Fee y descuentos
        'fee_details' => $datos['fee_details'] ?? [],
        'taxes' => $datos['taxes'] ?? [],
        'discounts' => $datos['discounts'] ?? [],

        // Metadata y otros
        'order' => $datos['order'] ?? null,
        'capture' => $datos['capture'] ?? null,
        'binary_mode' => $datos['binary_mode'] ?? null,
        'statement_descriptor' => $datos['statement_descriptor'] ?? null,

        // Información de la devolución (si aplica)
        'refunds' => $datos['refunds'] ?? [],
        'charges' => $datos['charges'] ?? [],
    ];
}

/**
 * CONSULTAR PREFERENCIA DE MERCADO PAGO
 * Obtiene información de la preferencia creada
 */
public function consultarPreferenciaMercadoPago(Request $request)
{
    try {
        $request->validate([
            'preference_id' => 'required|string',
        ]);

        $token = config('services.mercadopago.access_token');

        if (empty($token) || $token === 'null') {
            return response()->json([
                'success' => false,
                'message' => 'Token de Mercado Pago no configurado',
            ], 500);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/checkout/preferences/{$request->preference_id}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión con Mercado Pago: ' . $error,
            ], 500);
        }

        if ($httpCode !== 200 && $httpCode !== 201) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar la preferencia',
                'http_code' => $httpCode,
                'response' => json_decode($response, true),
            ], 500);
        }

        $preferencia = json_decode($response, true);

        // Buscar si tenemos un pago asociado a esta preferencia en nuestra BD
        $pago = Pago::where('preference_id', $request->preference_id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Preferencia obtenida exitosamente',
            'data' => [
                'preferencia' => $preferencia,
                'pago_asociado' => $pago ? [
                    'id' => $pago->id,
                    'monto' => $pago->monto_pagado,
                    'estado' => $pago->estado_pago,
                    'status' => $pago->status,
                    'no_pago_mp' => $pago->no_pago_mp,
                ] : null,
            ],
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al consultar preferencia',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}
}
