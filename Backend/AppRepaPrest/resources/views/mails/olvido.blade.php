<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-top: 40px;
            margin-bottom: 40px;
        }
        .header {
            background-color: #4A90E2;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
            color: #333333;
            line-height: 1.6;
        }
        .content p {
            margin-bottom: 20px;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            background-color: #4A90E2;
            color: #ffffff;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
        }
        .btn:hover {
            background-color: #357ABD;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #777777;
        }
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Restablecer Contraseña</h1>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $name }}</strong>,</p>
            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta asociada al correo <strong>{{ $email }}</strong>.</p>
            <p>Si fuiste tú, haz clic en el botón de abajo para continuar:</p>
            
            <div class="btn-container">
                <!-- Assuming a route named 'password.reset' exists or similar. 
                     If not, this href should be updated to the correct URL structure. -->
                <a href="{{ url('reset-password/'.$token) }}" class="btn">Restablecer Contraseña</a>
            </div>

            <p>O copia y pega el siguiente enlace en tu navegador:</p>
            <p style="word-break: break-all; color: #4A90E2;">{{ url('reset-password/'.$token) }}</p>
            
            <p>Si no solicitaste este cambio, puedes ignorar este correo de forma segura.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Nuestra Plataforma. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>