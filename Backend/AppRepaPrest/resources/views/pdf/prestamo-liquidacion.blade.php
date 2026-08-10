<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Liquidación - {{ $prestamo->folio }}</title>
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
            border-bottom: 3px solid #17a2b8;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .titulo-principal {
            font-size: 26px;
            font-weight: bold;
            color: #17a2b8;
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
        .badge-pagado {
            display: inline-block;
            padding: 4px 16px;
            background-color: #17a2b8;
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
            border-left: 4px solid #17a2b8;
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
        <div class="titulo-principal">✅ PRÉSTAMO LIQUIDADO</div>
        <div class="subtitulo">Folio: <strong>{{ $prestamo->folio }}</strong></div>
        <div class="subtitulo">Fecha de Liquidación: {{ \Carbon\Carbon::parse($prestamo->fecha_liquidacion)->format('d/m/Y H:i') }}</div>
    </div>

    <h3 style="color:#2c3e50; text-align:center;">Comprobante de Liquidación</h3>

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
            <td class="label">Monto Total Pagado</td>
            <td class="value"><strong style="color:#17a2b8; font-size:16px;">$ {{ number_format($prestamo->monto_total_pagar, 2) }}</strong></td>
        </tr>
        <tr>
            <td class="label">Número de Pagos Realizados</td>
            <td class="value">{{ $prestamo->pagos_realizados ?? $prestamo->numero_pagos }}</td>
        </tr>
        <tr>
            <td class="label">Total de Pagos Programados</td>
            <td class="value">{{ $prestamo->numero_pagos }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de Desembolso</td>
            <td class="value">{{ \Carbon\Carbon::parse($prestamo->fecha_desembolso)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de Liquidación</td>
            <td class="value">{{ \Carbon\Carbon::parse($prestamo->fecha_liquidacion)->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Estado</td>
            <td class="value"><span class="badge-pagado">PAGADO</span></td>
        </tr>
    </table>

    <div class="resumen-box">
        <strong>Estado de Cuenta:</strong>
        <ul style="margin:10px 0 0 20px;">
            <li>El préstamo ha sido liquidado en su totalidad.</li>
            <li>No existe saldo pendiente.</li>
            <li>La línea de crédito ha sido reactivada para futuras solicitudes.</li>
        </ul>
    </div>

    <div style="text-align:center; margin-top:30px;">
        <p style="font-size:14px; font-weight:bold; color:#28a745;">¡Felicidades! Has liquidado exitosamente tu préstamo.</p>
    </div>

    <div style="text-align:center; margin-top:20px;">
        <div class="firma-linea"></div>
        <p><strong>{{ $asesor->name ?? 'Asesor' }}</strong></p>
        <p style="font-size:10px; color:#777;">Asesor de Préstamos</p>
        <p style="font-size:10px; color:#999; margin-top:10px;">Este documento certifica la liquidación total del préstamo</p>
    </div>

    <div class="footer">
        Documento generado el {{ $fecha_generacion }} | Página {PAGE_NUM} de {PAGE_COUNT}
    </div>
</body>
</html>