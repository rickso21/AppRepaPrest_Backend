<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Aprobación - {{ $prestamo->folio }}</title>
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
            border-bottom: 3px solid #28a745;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .titulo-principal {
            font-size: 26px;
            font-weight: bold;
            color: #28a745;
        }
        .subtitulo {
            font-size: 16px;
            color: #555;
            margin-top: 5px;
        }
        .logo {
            margin-bottom: 10px;
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
        .badge-exito {
            display: inline-block;
            padding: 4px 16px;
            background-color: #28a745;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        .resumen-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .firma {
            margin-top: 40px;
            text-align: center;
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
        .firma-linea {
            width: 200px;
            border-top: 1px solid #333;
            margin: 20px auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h2 style="margin:0; color:#2c3e50;">Sistema de Préstamos</h2>
        </div>
        <div class="titulo-principal">✅ PRÉSTAMO APROBADO</div>
        <div class="subtitulo">Folio: <strong>{{ $prestamo->folio }}</strong></div>
        <div class="subtitulo">Fecha de Aprobación: {{ \Carbon\Carbon::parse($prestamo->fecha_aprobacion)->format('d/m/Y H:i') }}</div>
    </div>

    <h3 style="color:#2c3e50; text-align:center;">Detalles del Préstamo</h3>

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
            <td class="label">Monto Aprobado</td>
            <td class="value"><strong style="color:#28a745; font-size:16px;">$ {{ number_format($prestamo->monto_solicitado, 2) }}</strong></td>
        </tr>
        <tr>
            <td class="label">Monto Total a Pagar</td>
            <td class="value">$ {{ number_format($prestamo->monto_total_pagar, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Número de Pagos</td>
            <td class="value">{{ $prestamo->numero_pagos }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de Desembolso</td>
            <td class="value">{{ \Carbon\Carbon::parse($prestamo->fecha_desembolso)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de Primer Pago</td>
            <td class="value">{{ \Carbon\Carbon::parse($prestamo->fecha_primer_pago)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Estado</td>
            <td class="value"><span class="badge-exito">APROBADO</span></td>
        </tr>
    </table>

    @if($linea_credito)
    <h3>Línea de Crédito Asignada</h3>
    <table class="info-grid">
        <tr>
            <td class="label">Límite Aprobado</td>
            <td class="value">$ {{ number_format($linea_credito->limite_aprobado, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Límite Disponible</td>
            <td class="value">$ {{ number_format($linea_credito->limite_disponible, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Estatus</td>
            <td class="value">{{ $linea_credito->estatus_id == 1 ? 'Activa (Disponible)' : 'En Uso' }}</td>
        </tr>
    </table>
    @endif

    <div class="resumen-box">
        <strong>Resumen del Préstamo:</strong>
        <ul style="margin:10px 0 0 20px;">
            <li>El préstamo ha sido aprobado y se encuentra en proceso de desembolso.</li>
            <li>El primer pago deberá realizarse el día {{ \Carbon\Carbon::parse($prestamo->fecha_primer_pago)->format('d/m/Y') }}.</li>
            <li>Para cualquier consulta, comunicarse con su asesor asignado.</li>
        </ul>
    </div>

    <div class="firma">
        <div class="firma-linea"></div>
        <p><strong>{{ $asesor->name ?? 'Asesor' }}</strong></p>
        <p style="font-size:10px; color:#777;">Asesor de Préstamos</p>
        <p style="font-size:10px; color:#999; margin-top:10px;">Este documento es una constancia de aprobación de préstamo</p>
    </div>

    <div class="footer">
        Documento generado el {{ $fecha_generacion }} | Página {PAGE_NUM} de {PAGE_COUNT}
    </div>
</body>
</html>