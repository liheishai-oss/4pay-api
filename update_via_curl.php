<?php
// 通过HTTP请求更新订单状态
$orderNo = 'BY20251011144356C9F02907';

// 构建更新请求
$url = 'http://127.0.0.1:8787/api/v1/admin/order/update';
$data = [
    'order_no' => $orderNo,
    'status' => 3,
    'paid_time' => '2025-10-11 14:43:57',
    'third_party_order_no' => '2025101114435749488'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer 1b17a12d2ee81f71b1f096cc87341f1f'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP状态码: $httpCode\n";
echo "响应: $response\n";
