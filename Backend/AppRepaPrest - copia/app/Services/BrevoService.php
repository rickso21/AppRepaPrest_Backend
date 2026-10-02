<?php

namespace App\Services;

use SendinBlue\Client\Api\TransactionalEmailsApi;
use SendinBlue\Client\Configuration;
use SendinBlue\Client\Model\SendSmtpEmail;
use GuzzleHttp\Client;

class BrevoService
{
    protected $apiInstance;
    protected $senderEmail;
    protected $senderName;

    public function __construct()
    {
       $guzzleClient = new Client([
            'verify' => false, // Temporal para pruebas - DESPUÉS ACTIVAR SSL
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', env('BREVO_API_KEY'));


         $this->apiInstance = new TransactionalEmailsApi(
            $guzzleClient,
            $config
        );

        $this->senderEmail = env('BREVO_SENDER_EMAIL');
        $this->senderName = env('BREVO_SENDER_NAME');
    }

    /**
     * Enviar correo de solicitud de préstamo al asesor
     */
    public function sendLoanRequestEmail($prestamo, $usuario, $detalle)
    {
        try {
            $asesorEmail = env('BREVO_ASSESSOR_EMAIL');

            if (empty($asesorEmail)) {
                throw new \Exception('El email del asesor no está configurado en .env');
            }

            // Construir el HTML del correo
            $htmlContent = $this->buildLoanRequestHtml($prestamo, $usuario, $detalle);

            $sendSmtpEmail = new SendSmtpEmail([
                'sender' => [
                    'email' => $this->senderEmail,
                    'name' => $this->senderName
                ],
                'to' => [
                    [
                        'email' => $asesorEmail,
                        'name' => 'Asesor Financiero'
                    ]
                ],
                'subject' => "Nueva solicitud de préstamo - Folio: {$prestamo->folio}",
                'htmlContent' => $htmlContent,
                'headers' => [
                    'X-Mailin-custom' => 'loan_request',
                    'X-Mailin-msgid' => uniqid()
                ]
            ]);

            $response = $this->apiInstance->sendTransacEmail($sendSmtpEmail);

            \Log::info('Correo enviado exitosamente a asesor', [
                'folio' => $prestamo->folio,
                'message_id' => $response->getMessageId()
            ]);

            return $response;

        } catch (\Exception $e) {
            \Log::error('Error al enviar correo con Brevo', [
                'folio' => $prestamo->folio ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    /**
     * Construir el HTML del correo
     */
    private function buildLoanRequestHtml($prestamo, $usuario, $detalle)
    {
        // Variables del usuario - NOMBRE COMPLETO
        $nombre = $usuario->nombre ?? $usuario->name ?? 'Usuario';
        $apellido_p = $usuario->apellido_p ?? $usuario->apellido_paterno ?? '';
        $apellido_m = $usuario->apellido_m ?? $usuario->apellido_materno ?? '';
        $nombreCompleto = trim("{$nombre} {$apellido_p} {$apellido_m}");

        if (empty($nombreCompleto)) {
            $nombreCompleto = 'Usuario sin nombre completo';
        }

        $telefono = $usuario->telefono ?? $usuario->phone ?? 'No registrado';
        $email = $usuario->email ?? 'Sin email';
        $usuarioId = $usuario->id ?? 'N/A';

        // Variables del préstamo
        $folio = $prestamo->folio;
        $montoSolicitado = $prestamo->monto_solicitado;
        $numeroPagos = $prestamo->numero_pagos;
        $pagoQuincenal = $prestamo->pago_quincenal;
        $montoTotalPagar = $prestamo->monto_total_pagar;

        // ===== FECHAS DE PAGO =====
        $diasPorQuincena = 15;
        $fechaBase = now();

        if (!empty($prestamo->fecha_aprobacion)) {
            $fechaAprobacion = $prestamo->fecha_aprobacion instanceof \Carbon\Carbon
                ? $prestamo->fecha_aprobacion
                : \Carbon\Carbon::parse($prestamo->fecha_aprobacion);
        } else {
            $fechaAprobacion = now()->addHours(48);
        }

        $fechaPrimerPago = $fechaAprobacion->copy()->addDays($diasPorQuincena)->format('d/m/Y');
        $fechaUltimoPago = $fechaAprobacion->copy()->addDays($numeroPagos * $diasPorQuincena)->format('d/m/Y');

        // Calcular total de intereses desde el desglose
        $totalInteres = 0;
        $totalIva = 0;
        $desglose = $detalle['desglose_pagos'] ?? $detalle['desglose_quincenal'] ?? [];

        // Construir filas del desglose
        $rows = '';
        if (!empty($desglose) && is_array($desglose)) {
            foreach ($desglose as $pago) {
                $quincena = $pago['quincena'] ?? $pago['numero'] ?? '?';
                $capital = $pago['capital'] ?? 0;
                $interes = $pago['interes'] ?? 0;
                $iva = $pago['iva'] ?? 0;
                $pagoTotal = $pago['pago_total'] ?? $pago['monto_quincenal'] ?? 0;

                $totalInteres += $interes;
                $totalIva += $iva;

                $rows .= "
                            <tr style=\"border-bottom: 1px solid #e9ecef;\">
                                <td style=\"padding: 10px 12px; text-align: center; font-weight: 500;\">#{$quincena}</td>
                                <td style=\"padding: 10px 12px; text-align: right;\">$ {$capital}</td>
                                <td style=\"padding: 10px 12px; text-align: right;\">$ {$interes}</td>
                                <td style=\"padding: 10px 12px; text-align: right;\">$ {$iva}</td>
                                <td style=\"padding: 10px 12px; text-align: right; font-weight: bold; color: #0056b3;\">$ {$pagoTotal}</td>
                            </tr>";
            }
        }

        if (empty($rows)) {
            $rows = '
                            <tr>
                                <td colspan="5" style="padding: 15px; text-align: center; color: #999;">
                                    Sin desglose de pagos disponible
                                </td>
                            </tr>';
        }

        $fechaSolicitud = $prestamo->fecha_solicitud instanceof \Carbon\Carbon
            ? $prestamo->fecha_solicitud->format('d/m/Y H:i')
            : date('d/m/Y H:i', strtotime($prestamo->fecha_solicitud));

        $tiempoEstimado = now()->addHours(48)->format('d/m/Y H:i');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nueva Solicitud de Préstamo - {$folio}</title>
            <style>
                @media only screen and (max-width: 600px) {
                    .container { padding: 15px !important; }
                    .table-responsive { overflow-x: auto !important; }
                    .badge { font-size: 12px !important; padding: 4px 12px !important; }
                    .amount { font-size: 16px !important; }
                    .total-amount { font-size: 18px !important; }
                }
            </style>
        </head>
        <body style="font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px;">
            <div class="container" style="max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 35px 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">

                <!-- ===== HEADER ===== -->
                <div style="text-align: center; border-bottom: 3px solid #1a73e8; padding-bottom: 25px; margin-bottom: 25px;">

<img src="https://i.imgur.com/zXfQ9wL.png" alt="Logo" style="width: 90px; height: 90px; object-fit: cover; border-radius: 50%; display: block; margin: 0 auto 15px auto; border: 0;">                <h1 style="color: #1a73e8; margin: 0; font-size: 26px; font-weight: 700;">Nueva Solicitud de Préstamo</h1>
                    <div style="background: #e8f0fe; border-radius: 20px; padding: 6px 20px; display: inline-block; margin-top: 12px;">
                        <span style="color: #1a73e8; font-weight: 600; font-size: 15px; letter-spacing: 0.5px;">FOLIO: {$folio}</span>
                    </div>
                    <p style="color: #888; font-size: 13px; margin-top: 10px;">
                        📅 {$fechaSolicitud}
                    </p>
                </div>

                <!-- ===== BADGE DE ESTADO ===== -->
                <div style="text-align: center; margin-bottom: 28px;">
                    <span class="badge" style="background: #fff3cd; color: #856404; padding: 8px 28px; border-radius: 30px; font-weight: 700; font-size: 14px; border: 2px solid #ffc107;">
                        ⏳ Pendiente de Revisión
                    </span>
                </div>

                <!-- ===== INFORMACIÓN DEL SOLICITANTE ===== -->
                <div style="margin-bottom: 25px; background: #f8f9fa; padding: 20px 22px; border-radius: 10px; border-left: 5px solid #1a73e8;">
                    <h3 style="color: #333; margin: 0 0 14px 0; font-size: 16px;">
                        👤 Información del Solicitante
                    </h3>
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <tr>
                            <td style="padding: 5px 0; color: #666; width: 38%; font-weight: 600; text-align: right; padding-right: 15px;">Nombre Completo:</td>
                            <td style="padding: 5px 0; color: #222; text-align: left;">{$nombreCompleto}</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; color: #666; font-weight: 600; text-align: right; padding-right: 15px;">Email:</td>
                            <td style="padding: 5px 0; color: #222; text-align: left;">{$email}</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; color: #666; font-weight: 600; text-align: right; padding-right: 15px;">Teléfono:</td>
                            <td style="padding: 5px 0; color: #222; text-align: left;">{$telefono}</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; color: #666; font-weight: 600; text-align: right; padding-right: 15px;">ID Usuario:</td>
                            <td style="padding: 5px 0; color: #222; text-align: left;">#{$usuarioId}</td>
                        </tr>
                    </table>
                </div>

                <!-- ===== DETALLES DEL PRÉSTAMO ===== -->
                <div style="margin-bottom: 25px; background: linear-gradient(135deg, #e8f4fd 0%, #f0f8ff 100%); padding: 20px 22px; border-radius: 10px; border-left: 5px solid #28a745;">
                    <h3 style="color: #333; margin: 0 0 14px 0; font-size: 16px; text-align: left;">
                        💰 Detalles del Préstamo
                    </h3>
                    <!-- Tabla centrada con max-width -->
                    <table style="width: 100%; max-width: 450px; margin: 0 auto; border-collapse: collapse; font-size: 14px;">
                        <tr>
                            <td style="padding: 6px 0; color: #666; width: 45%; font-weight: 600; text-align: right; padding-right: 15px;">Monto Solicitado:</td>
                            <td style="padding: 6px 0; color: #1a73e8; font-weight: 700; font-size: 18px; text-align: left;">$ {$montoSolicitado}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #666; font-weight: 600; text-align: right; padding-right: 15px;">Número de Pagos:</td>
                            <td style="padding: 6px 0; color: #222; text-align: left;">{$numeroPagos} quincena(s)</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #666; font-weight: 600; text-align: right; padding-right: 15px;">Pago por Quincena:</td>
                            <td style="padding: 6px 0; color: #222; font-weight: 600; text-align: left;">$ {$pagoQuincenal}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #666; font-weight: 600; text-align: right; padding-right: 15px;">Interés Total:</td>
                            <td style="padding: 6px 0; color: #dc3545; text-align: left;">$ {$totalInteres}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #666; font-weight: 600; text-align: right; padding-right: 15px;">IVA Total:</td>
                            <td style="padding: 6px 0; color: #222; text-align: left;">$ {$totalIva}</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 10px 0 6px 0; border-top: 2px solid #28a745; text-align: center;">
                                <span style="color: #333; font-weight: 700; font-size: 16px;">Monto Total a Pagar:</span>
                                <span style="color: #28a745; font-weight: 800; font-size: 22px; display: block; margin-top: 2px;">$ {$montoTotalPagar}</span>
                            </td>
                        </tr>
                    </table>

                    <!-- ===== FECHAS DE PAGO ===== -->
                    <div style="margin-top: 16px; padding-top: 14px; border-top: 2px dashed #cce5ff;">
                        <h4 style="color: #333; margin: 0 0 10px 0; font-size: 14px; text-align: center;">📅 Fechas de Pago Estimadas</h4>
                        <table style="width: 100%; max-width: 350px; margin: 0 auto; border-collapse: collapse; font-size: 13px;">
                            <tr>
                                <td style="padding: 4px 10px; color: #666; font-weight: 600; text-align: right;">1er Pago:</td>
                                <td style="padding: 4px 10px; color: #1a73e8; font-weight: 600; text-align: left;">{$fechaPrimerPago}</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 10px; color: #666; font-weight: 600; text-align: right;">Último Pago:</td>
                                <td style="padding: 4px 10px; color: #1a73e8; font-weight: 600; text-align: left;">{$fechaUltimoPago}</td>
                            </tr>
                        </table>
                        <p style="margin: 8px 0 0 0; font-size: 12px; color: #888; font-style: italic; text-align: center;">
                            ⏱️ Fechas calculadas a partir de la fecha de aprobación del préstamo.
                        </p>
                    </div>
                </div>

                <!-- ===== DESGLOSE DE PAGOS ===== -->
                <div style="margin-bottom: 25px; background: #ffffff; border: 1px solid #e9ecef; border-radius: 10px; overflow: hidden;">
                    <div style="background: #f1f3f5; padding: 12px 18px; border-bottom: 1px solid #e9ecef;">
                        <h4 style="margin: 0; color: #333; font-size: 15px; text-align: center;">📊 Desglose de Pagos</h4>
                    </div>
                    <div class="table-responsive" style="padding: 0; overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 10px 12px; text-align: center; border-bottom: 2px solid #dee2e6; color: #555; font-weight: 700;">Quincena</th>
                                    <th style="padding: 10px 12px; text-align: right; border-bottom: 2px solid #dee2e6; color: #555; font-weight: 700;">Capital</th>
                                    <th style="padding: 10px 12px; text-align: right; border-bottom: 2px solid #dee2e6; color: #555; font-weight: 700;">Interés</th>
                                    <th style="padding: 10px 12px; text-align: right; border-bottom: 2px solid #dee2e6; color: #555; font-weight: 700;">IVA</th>
                                    <th style="padding: 10px 12px; text-align: right; border-bottom: 2px solid #dee2e6; color: #555; font-weight: 700;">Pago Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$rows}
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; border-top: 2px solid #dee2e6; font-weight: 700;">
                                    <td style="padding: 10px 12px; text-align: center; color: #333;">TOTAL</td>
                                    <td style="padding: 10px 12px; text-align: right; color: #333;">$ {$montoSolicitado}</td>
                                    <td style="padding: 10px 12px; text-align: right; color: #dc3545;">$ {$totalInteres}</td>
                                    <td style="padding: 10px 12px; text-align: right; color: #333;">$ {$totalIva}</td>
                                    <td style="padding: 10px 12px; text-align: right; color: #1a73e8; font-size: 15px;">$ {$montoTotalPagar}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- ===== ATENCIÓN ===== -->
                <div style="margin-bottom: 25px; background: #fff8e1; border: 2px solid #ffc107; border-radius: 10px; padding: 18px 22px;">
                    <h4 style="margin: 0 0 12px 0; color: #856404; display: flex; align-items: center; font-size: 16px;">
                        <span style="font-size: 26px; margin-right: 12px;">⚠️</span>
                        ATENCIÓN - Pasos a Seguir
                    </h4>
                    <div style="color: #856404; font-size: 14px; line-height: 1.7;">
                        <p style="margin: 0 0 10px 0; font-weight: 600;">Para dar seguimiento a esta solicitud, es necesario:</p>
                        <ol style="margin: 0 0 8px 20px; padding-left: 10px;">
                            <li style="margin-bottom: 8px;">
                                <strong>Ingresar a la aplicación</strong> y verificar la información compartida por el usuario.
                            </li>
                            <li style="margin-bottom: 8px;">
                                <strong>Contactar al usuario</strong> vía WhatsApp o llamada telefónica para:
                                <ul style="margin: 4px 0 0 22px; list-style-type: circle;">
                                    <li>Corroborar sus datos personales</li>
                                    <li>Verificar la información proporcionada</li>
                                </ul>
                            </li>
                            <li style="margin-bottom: 8px;">
                                <strong>Solicitar la siguiente documentación</strong>:
                                <ul style="margin: 4px 0 0 22px; list-style-type: circle;">
                                    <li>📄 Identificación oficial (INE/IFE, Pasaporte, Cédula)</li>
                                    <li>🏦 Estado de cuenta bancario (donde se depositará el préstamo)</li>
                                </ul>
                            </li>
                        </ol>
                        <div style="margin-top: 12px; background: #ffffff; padding: 10px 14px; border-radius: 6px; border-left: 4px solid #ffc107; font-size: 13px;">
                            💡 <strong>Importante:</strong> La aprobación del préstamo está sujeta a la validación de estos documentos.
                        </div>
                    </div>
                </div>

                <!-- ===== TIEMPO ESTIMADO ===== -->
                <div style="text-align: center; padding: 16px; background: #f8f9fa; border-radius: 10px; margin-bottom: 18px;">
                    <p style="color: #666; font-size: 14px; margin: 0;">
                        ⏰ Tiempo estimado de respuesta: <strong style="color: #1a73e8;">{$tiempoEstimado}</strong>
                    </p>
                    <p style="color: #ff0000; font-size: 13px; margin: 4px 0 0 0;">
                        Por favor, revisa esta solicitud en el panel de administración, recuerda que un prestamo cuenta a partir de que se aprobo y se deposito el dinero al cliente.
                    </p>
                </div>

                <!-- ===== FOOTER ===== -->
                <div style="border-top: 1px solid #e9ecef; padding-top: 18px; text-align: center; color: #aaa; font-size: 12px;">
                    <p style="margin: 0;">
                        Este correo es generado automáticamente por el sistema de préstamos.
                    </p>
                    <p style="margin: 4px 0 0 0; font-weight: 500; color: #888;">
                        Delivery Sobre Ruedas S.A de C.V © 2026
                    </p>
                </div>

            </div>
        </body>
        </html>
HTML;
    }


        /**
     * Enviar correo de aprobación al usuario con el PDF adjunto
     */
    public function sendLoanApprovalEmail($prestamo, $usuario, $rutaPdf)
    {
        try {
            // Validar que el usuario tenga email
            if (empty($usuario->email)) {
                \Log::warning('El usuario no tiene correo electrónico registrado', [
                    'usuario_id' => $usuario->id,
                    'folio' => $prestamo->folio
                ]);
                return false;
            }

            // Construir el HTML del correo para el usuario
            $htmlContent = $this->buildLoanApprovalHtml($prestamo, $usuario);

            // Preparar el archivo adjunto (PDF)
            $pdfBinary = file_get_contents($rutaPdf);
            $pdfName = "Contrato_Prestamo_{$prestamo->folio}.pdf";

            $sendSmtpEmail = new SendSmtpEmail([
                'sender' => [
                    'email' => $this->senderEmail,
                    'name' => $this->senderName
                ],
                'to' => [
                    [
                        'email' => $usuario->email,
                        'name' => $usuario->nombre ?? 'Cliente'
                    ]
                ],
                'subject' => "¡Tu préstamo ha sido aprobado! - Folio: {$prestamo->folio}",
                'htmlContent' => $htmlContent,
                'attachment' => [
                    [
                        'name' => $pdfName,
                        'content' => base64_encode($pdfBinary)
                    ]
                ],
                'headers' => [
                    'X-Mailin-custom' => 'loan_approval',
                    'X-Mailin-msgid' => uniqid()
                ]
            ]);

            $response = $this->apiInstance->sendTransacEmail($sendSmtpEmail);

            \Log::info('Correo de aprobación enviado exitosamente al usuario', [
                'folio' => $prestamo->folio,
                'usuario_id' => $usuario->id,
                'message_id' => $response->getMessageId()
            ]);

            return $response;

        } catch (\Exception $e) {
            \Log::error('Error al enviar correo de aprobación al usuario', [
                'folio' => $prestamo->folio ?? null,
                'usuario_id' => $usuario->id ?? null,
                'error' => $e->getMessage()
            ]);
            // No lanzamos la excepción para no interrumpir el flujo principal
            return false;
        }
    }

    /**
     * Construir el HTML del correo de aprobación para el usuario
     */
    private function buildLoanApprovalHtml($prestamo, $usuario)
    {
        // Datos del usuario
        $nombre = $usuario->nombre ?? $usuario->name ?? 'Cliente';
        $folio = $prestamo->folio;
        $monto = number_format($prestamo->monto_solicitado, 2);
        $totalPagar = number_format($prestamo->monto_total_pagar, 2);
        $pagoQuincenal = number_format($prestamo->pago_quincenal, 2);
        $numeroPagos = $prestamo->numero_pagos;

        // Fechas formateadas
        $fechaAprobacion = $prestamo->fecha_aprobacion instanceof \Carbon\Carbon
            ? $prestamo->fecha_aprobacion->format('d/m/Y')
            : date('d/m/Y', strtotime($prestamo->fecha_aprobacion));

        $fechaDesembolso = $prestamo->fecha_desembolso instanceof \Carbon\Carbon
            ? $prestamo->fecha_desembolso->format('d/m/Y')
            : date('d/m/Y', strtotime($prestamo->fecha_desembolso));

        $fechaPrimerPago = $prestamo->fecha_primer_pago instanceof \Carbon\Carbon
            ? $prestamo->fecha_primer_pago->format('d/m/Y')
            : date('d/m/Y', strtotime($prestamo->fecha_primer_pago));

        $fechaUltimoPago = $prestamo->fecha_ultimo_pago instanceof \Carbon\Carbon
            ? $prestamo->fecha_ultimo_pago->format('d/m/Y')
            : date('d/m/Y', strtotime($prestamo->fecha_ultimo_pago));

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Préstamo Aprobado - {$folio}</title>
        </head>
        <body style="font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">

                <!-- HEADER -->
                <div style="text-align: center; border-bottom: 3px solid #28a745; padding-bottom: 20px; margin-bottom: 25px;">
                    <h1 style="color: #28a745; margin: 0; font-size: 24px;">✅ ¡Préstamo Aprobado!</h1>
                    <p style="color: #666; margin-top: 8px; font-size: 14px;">Folio: <strong>{$folio}</strong></p>
                </div>

                <!-- MENSAJE PERSONALIZADO -->
                <div style="text-align: center; margin-bottom: 25px;">
                    <p style="font-size: 16px; color: #333; line-height: 1.6;">
                        Hola <strong>{$nombre}</strong>,<br>
                        ¡Felicidades! Tu solicitud de préstamo ha sido <strong style="color: #28a745;">aprobada</strong>.
                        El dinero será depositado en tu cuenta bancaria.
                    </p>
                </div>

                <!-- DETALLES DEL PRÉSTAMO -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 25px;">
                    <h3 style="color: #333; margin: 0 0 15px 0; text-align: center; font-size: 16px;">💰 Detalles del Préstamo</h3>
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <tr>
                            <td style="padding: 6px 0; color: #666; font-weight: 600; width: 50%;">Monto Solicitado:</td>
                            <td style="padding: 6px 0; color: #1a73e8; font-weight: 700;">$ {$monto}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #666; font-weight: 600;">Pago Quincenal:</td>
                            <td style="padding: 6px 0; color: #333;">$ {$pagoQuincenal}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #666; font-weight: 600;">Número de Pagos:</td>
                            <td style="padding: 6px 0; color: #333;">{$numeroPagos} quincena(s)</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #666; font-weight: 600;">Total a Pagar:</td>
                            <td style="padding: 6px 0; color: #dc3545; font-weight: 700;">$ {$totalPagar}</td>
                        </tr>
                    </table>
                </div>

                <!-- FECHAS IMPORTANTES -->
                <div style="background: #e8f4fd; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid #1a73e8;">
                    <h4 style="color: #1a73e8; margin: 0 0 12px 0; font-size: 15px;">📅 Calendario de Pagos</h4>
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <tr>
                            <td style="padding: 4px 0; color: #555; font-weight: 600; width: 50%;">Fecha de Aprobación:</td>
                            <td style="padding: 4px 0; color: #333;">{$fechaAprobacion}</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #555; font-weight: 600;">Fecha de Desembolso:</td>
                            <td style="padding: 4px 0; color: #333;">{$fechaDesembolso}</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #555; font-weight: 600;">Primer Pago:</td>
                            <td style="padding: 4px 0; color: #dc3545; font-weight: 600;">{$fechaPrimerPago}</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #555; font-weight: 600;">Último Pago:</td>
                            <td style="padding: 4px 0; color: #333;">{$fechaUltimoPago}</td>
                        </tr>
                    </table>
                </div>

                <!-- AVISO DEL ADJUNTO -->
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
                    <p style="margin: 0; color: #856404; font-size: 14px; text-align: center;">
                        📎 <strong>Documento adjunto:</strong> Encontrarás el contrato de préstamo con todos los detalles legales en el PDF adjunto a este correo.
                    </p>
                </div>

                <!-- FOOTER -->
                <div style="border-top: 1px solid #e9ecef; padding-top: 15px; text-align: center; color: #aaa; font-size: 12px;">
                    <p style="margin: 0;">
                        Este correo es generado automáticamente por el sistema de préstamos.
                    </p>
                    <p style="margin: 4px 0 0 0; font-weight: 500; color: #888;">
                        Delivery Sobre Ruedas S.A de C.V © 2026
                    </p>
                </div>

            </div>
        </body>
        </html>
HTML;
    }

        /**
     * Enviar correo de liquidación al usuario con el PDF adjunto
     */
    public function sendLoanLiquidatedEmail($prestamo, $usuario, $rutaPdf)
    {
        try {
            if (empty($usuario->email)) {
                \Log::warning('El usuario no tiene correo electrónico registrado', ['usuario_id' => $usuario->id, 'folio' => $prestamo->folio]);
                return false;
            }

            $htmlContent = $this->buildLoanLiquidatedHtml($prestamo, $usuario);

            $pdfBinary = file_get_contents($rutaPdf);
            $pdfName = "Finiquito_Prestamo_{$prestamo->folio}.pdf";

            $sendSmtpEmail = new SendSmtpEmail([
                'sender' => ['email' => $this->senderEmail, 'name' => $this->senderName],
                'to' => [['email' => $usuario->email, 'name' => $usuario->nombre ?? 'Cliente']],
                'subject' => "¡Préstamo liquidado! - Folio: {$prestamo->folio}",
                'htmlContent' => $htmlContent,
                'attachment' => [['name' => $pdfName, 'content' => base64_encode($pdfBinary)]]
            ]);

            $response = $this->apiInstance->sendTransacEmail($sendSmtpEmail);

            \Log::info('Correo de liquidación enviado al usuario', [
                'folio' => $prestamo->folio, 'usuario_id' => $usuario->id
            ]);

            return $response;

        } catch (\Exception $e) {
            \Log::error('Error al enviar correo de liquidación', ['folio' => $prestamo->folio, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Enviar correo de rechazo al usuario con el PDF adjunto
     */
    public function sendLoanRejectedEmail($prestamo, $usuario, $rutaPdf)
    {
        try {
            if (empty($usuario->email)) {
                \Log::warning('El usuario no tiene correo electrónico registrado', ['usuario_id' => $usuario->id, 'folio' => $prestamo->folio]);
                return false;
            }

            $htmlContent = $this->buildLoanRejectedHtml($prestamo, $usuario);

            $pdfBinary = file_get_contents($rutaPdf);
            $pdfName = "Rechazo_Prestamo_{$prestamo->folio}.pdf";

            $sendSmtpEmail = new SendSmtpEmail([
                'sender' => ['email' => $this->senderEmail, 'name' => $this->senderName],
                'to' => [['email' => $usuario->email, 'name' => $usuario->nombre ?? 'Cliente']],
                'subject' => "Actualización de tu solicitud - Folio: {$prestamo->folio}",
                'htmlContent' => $htmlContent,
                'attachment' => [['name' => $pdfName, 'content' => base64_encode($pdfBinary)]]
            ]);

            $response = $this->apiInstance->sendTransacEmail($sendSmtpEmail);

            \Log::info('Correo de rechazo enviado al usuario', [
                'folio' => $prestamo->folio, 'usuario_id' => $usuario->id
            ]);

            return $response;

        } catch (\Exception $e) {
            \Log::error('Error al enviar correo de rechazo', ['folio' => $prestamo->folio, 'error' => $e->getMessage()]);
            return false;
        }
    }


        private function buildLoanLiquidatedHtml($prestamo, $usuario)
    {
        $nombre = $usuario->nombre ?? $usuario->name ?? 'Cliente';
        $folio = $prestamo->folio;
        $totalPagado = number_format($prestamo->monto_total_pagar, 2);
        $fechaLiquidacion = $prestamo->fecha_liquidacion instanceof \Carbon\Carbon
            ? $prestamo->fecha_liquidacion->format('d/m/Y H:i')
            : date('d/m/Y H:i', strtotime($prestamo->fecha_liquidacion));

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Préstamo Liquidado - {$folio}</title></head>
        <body style="font-family: 'Segoe UI', Arial; background: #f0f2f5; margin:0; padding:20px;">
            <div style="max-width:600px; margin:0 auto; background:#fff; border-radius:12px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                <div style="text-align:center; border-bottom:3px solid #28a745; padding-bottom:20px; margin-bottom:25px;">
                    <h1 style="color:#28a745; margin:0;">🎉 Felicidades ¡Préstamo Liquidado!</h1>
                    <p style="color:#666; margin-top:8px;">Folio: <strong>{$folio}</strong></p>
                </div>
                <div style="text-align:center; margin-bottom:25px;">
                    <p style="font-size:16px; color:#333; line-height:1.6;">
                        Hola <strong>{$nombre}</strong>,<br>
                        ¡Felicidades! Has liquidado tu préstamo en su totalidad.<br>
                        <strong style="color:#28a745;">Monto total pagado: $ {$totalPagado}</strong>
                    </p>
                </div>
                <div style="background:#f8f9fa; padding:15px; border-radius:10px; text-align:center; margin-bottom:20px;">
                    <strong>Fecha de liquidación:</strong> {$fechaLiquidacion}
                </div>
                <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:15px; margin-bottom:20px;">
                    <p style="margin:0; color:#856404; font-size:14px; text-align:center;">
                        📎 <strong>Documento adjunto:</strong> Tu recibo de finiquito y liquidación total.
                    </p>
                </div>
                <div style="border-top:1px solid #e9ecef; padding-top:15px; text-align:center; color:#aaa; font-size:12px;">
                    <p style="margin:0;">Delivery Sobre Ruedas S.A de C.V © 2026</p>
                </div>
            </div>
        </body>
        </html>
HTML;
    }

    private function buildLoanRejectedHtml($prestamo, $usuario)
    {
        $nombre = $usuario->nombre ?? $usuario->name ?? 'Cliente';
        $folio = $prestamo->folio;
        $motivo = $prestamo->motivo_rechazo ?? 'No se especificó un motivo';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Actualización - {$folio}</title></head>
        <body style="font-family: 'Segoe UI', Arial; background: #f0f2f5; margin:0; padding:20px;">
            <div style="max-width:600px; margin:0 auto; background:#fff; border-radius:12px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                <div style="text-align:center; border-bottom:3px solid #dc3545; padding-bottom:20px; margin-bottom:25px;">
                    <h1 style="color:#dc3545; margin:0;">❌ Solicitud No Aprobada</h1>
                    <p style="color:#666; margin-top:8px;">Folio: <strong>{$folio}</strong></p>
                </div>
                <div style="text-align:center; margin-bottom:25px;">
                    <p style="font-size:16px; color:#333; line-height:1.6;">
                        Hola <strong>{$nombre}</strong>,<br>
                        Lamentamos informarte que tu solicitud de préstamo no ha sido aprobada en esta ocasión.
                    </p>
                </div>
                <div style="background:#f8d7da; border:1px solid #f5c6cb; border-radius:10px; padding:20px; margin-bottom:20px;">
                    <h4 style="color:#721c24; margin:0 0 10px 0;">📝 Motivo del rechazo:</h4>
                    <p style="color:#721c24; margin:0; font-size:15px;">{$motivo}</p>
                </div>
                <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:15px; margin-bottom:20px;">
                    <p style="margin:0; color:#856404; font-size:14px; text-align:center;">
                        📎 <strong>Documento adjunto:</strong> Constancia oficial de rechazo con los detalles.
                    </p>
                </div>
                <div style="border-top:1px solid #e9ecef; padding-top:15px; text-align:center; color:#aaa; font-size:12px;">
                    <p style="margin:0;">Delivery Sobre Ruedas S.A de C.V © 2026</p>
                </div>
            </div>
        </body>
        </html>
HTML;
    }
}
