<?php
// test_merchant_order.php

$secret = '1';
$body = '{"resource":"https://api.mercadolibre.com/merchant_orders/43599655213","topic":"merchant_order"}';
$timestamp = time();

$manifest = "id:{$body};ts:{$timestamp};";
$hash = hash_hmac('sha256', $manifest, $secret);
$xSignature = "ts={$timestamp}, v1={$hash}";

echo "========================================\n";
echo "🧪 PROBANDO MERCHANT ORDER\n";
echo "========================================\n";
echo "📦 Body: {$body}\n";
echo "✍️  Firma: {$xSignature}\n";
echo "========================================\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/verificar_pago');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-signature: ' . $xSignature,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "📡 Código HTTP: {$httpCode}\n";
echo "📥 Respuesta: {$response}\n";
echo "========================================\n";
