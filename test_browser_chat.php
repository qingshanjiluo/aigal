<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
error_reporting(0);
require_once 'db.php';

$userId = getCurrentUserId();
echo "User ID: {$userId}\n";

$customKey = loadUserData($userId, 'deepseek_api_key');
echo "Custom Key: " . ($customKey ? substr($customKey, 0, 20) . "..." : "无") . "\n";

$defaultKey = DEEPSEEK_API_KEY;
echo "Default Key: " . substr($defaultKey, 0, 20) . "...\n";

$actualKey = $customKey ?: $defaultKey;
echo "Actual Key used: " . substr($actualKey, 0, 20) . "...\n";

// 测试调用
$data = [
    'model' => 'deepseek-chat',
    'messages' => [['role' => 'user', 'content' => '你好']],
    'temperature' => 0.8,
    'max_tokens' => 100
];
$ch = curl_init('https://api.deepseek.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $actualKey
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "\nHTTP Code: {$httpCode}\n";
echo "cURL Error: " . ($curlError ?: '无') . "\n";
echo "Response (前300字符):\n";
echo mb_substr($response, 0, 300) . "\n";
