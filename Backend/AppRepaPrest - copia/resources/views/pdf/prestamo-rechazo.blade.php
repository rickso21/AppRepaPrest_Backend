<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Notificación de Rechazo - {{ $prestamo->folio }}</title>
    <style>
        @page {
            margin: 20mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #dc3545;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .titulo-principal {
            font-size: 26px;
            font-weight: bold;
            color: #dc3545;
        }
        .subtitulo {
            font-size: 16px;
            color: #555;
            margin-top: 5px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-grid td {
            padding: 10px 12px;
            border: 1px solid #ddd;
        }
        .label {
            font-weight: bold;
            background-color: #f8f9fa;
            width: 35%;
        }
        .value {
            width: 65%;
        }
        .badge-rechazo {
            display: inline-block;
            padding: 4px 16px;
            background-color: #dc3545;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        .motivo-box {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 3px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #777;
            padding: 10px;
            border-top: 1px solid #ddd;
        }
        .info-extra {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="titulo-principal">PRÉSTAMO RECHAZADO</div>
        <div class="subtitulo">Folio: <strong>{{ $prestamo->folio }}</strong></div>
        <div class="subtitulo">Fecha de Rechazo: {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</div>
    </div>

    <h3 style="color:#2c3e50; text-align:center;">Notificación Oficial</h3>

    <table class="info-grid">
          <tr>
            <td class="label">Cliente</td>
            <td class="value">
                <strong>
                    {{ $usuario->nombre ?? '' }} 
                    {{ $usuario->apellido_p ?? '' }} 
                    {{ $usuario->apellido_m ?? '' }}
                </strong>
            </td>
        </tr>
        <tr>
            <td class="label">Correo Electrónico</td>
            <td class="value">{{ $usuario->email ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Monto Solicitado</td>
            <td class="value">$ {{ number_format($prestamo->monto_solicitado, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de Solicitud</td>
            <td class="value">{{ \Carbon\Carbon::parse($prestamo->fecha_solicitud)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Estado Final</td>
            <td class="value"><span class="badge-rechazo">RECHAZADO</span></td>
        </tr>
    </table>

    @if($motivo_rechazo)
    <div class="motivo-box">
        <strong style="color:#dc3545;">Motivo del Rechazo:</strong><br>
        <p style="margin-top:10px;">{{ $motivo_rechazo }}</p>
    </div>
    @endif

    <div style="margin-top: 30px;">
        <p style="font-size: 14px; color: #666;">
            Estimado cliente, lamentamos informarle que su solicitud de préstamo no ha sido aprobada en esta ocasión.
        </p>
        
        <div class="info-extra">
            <strong>Información Adicional:</strong>
            <ul style="margin:10px 0 0 20px;">
                <li>Puede volver a solicitar un préstamo en cualquier momento.</li>
                <li>Si tiene alguna duda, comuníquese con su asesor asignado.</li>
                <li>Este documento es una constancia oficial del rechazo.</li>
            </ul>
        </div>
    </div>

    <div style="text-align:center; margin-top:30px;">
        <div style="width:200px; border-top:1px solid #333; margin:20px auto;"></div>
        <p><strong>{{ $asesor->name ?? 'Asesor' }}</strong></p>
        <p style="font-size:10px; color:#777;">Asesor de Préstamos</p>
        <p style="font-size:10px; color:#999; margin-top:10px;">Este documento es una notificación oficial de rechazo</p>
    </div>

    <div class="footer">
        Documento generado el {{ $fecha_generacion }} | Página {PAGE_NUM} de {PAGE_COUNT}
    </div>
</body>
</html>