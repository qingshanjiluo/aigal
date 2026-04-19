<?php
session_start();
$_SESSION['user_id'] = 1;

require_once 'db.php';
require_once 'game.php';

$apiKey = getApiKey();
echo "API Key: " . substr($apiKey, 0, 20) . "...\n\n";

// 模拟chat请求的完整数据
$history = [
    ['speaker' => '系统', 'text' => '你在校园里遇到了一个女生'],
    ['speaker' => '小爱', 'text' => '【喜】你好呀！我是小爱'],
    ['speaker' => '玩家', 'text' => '你好']
];
$characters = [
    ['id' => 'char_1', 'name' => '小爱', 'prompt' => '开朗学妹', 'emoji' => '😊', 'affection' => 50]
];
$affection = ['char_1' => 50];
$playerInput = '今天天气真好';
$currentScene = '校园';
$currentChapter = 1;
$chapterTitle = '第一章：初遇';
$importantEvents = [];

$settings = getUserSettings(1);
$groupMode = $settings['group_mode'] ?? false;
$bg = $settings['story_background'] ?? '现代校园';
$globalPrompt = $settings['global_prompt'] ?? '';
$autoScene = $settings['auto_scene'] ?? true;
$autoOptions = $settings['auto_options'] ?? true;

$context = "";
foreach (array_slice($history, -10) as $msg) {
    $context .= "{$msg['speaker']}: {$msg['text']}\n";
}
$charInfo = "";
foreach ($characters as $c) {
    $aff = $affection[$c['id']] ?? 50;
    $charInfo .= "{$c['name']}(好感度{$aff}) ";
}

$memoryPrompt = "";
if (!empty($importantEvents)) {
    $memoryPrompt .= "重要记忆：\n";
    foreach ($importantEvents as $key => $event) {
        $memoryPrompt .= "- {$event['description']}\n";
    }
}

$extra = "";
if ($globalPrompt) $extra .= "全局提示：{$globalPrompt}\n";
if (!$autoScene) $extra .= "不要自动生成新场景，保持当前场景。\n";
if (!$autoOptions) $extra .= "不要生成选项。\n";
if ($currentChapter) $extra .= "当前章节：第{$currentChapter}章 {$chapterTitle}\n";

$optionsHint = $autoOptions ? '包含"options":["选项1","选项2"]' : '不要生成options字段';
$sceneHint = $autoScene ? 'new_scene可为空表示不变' : 'new_scene必须为空';
$prompt = "故事背景：{$bg}\n当前场景：{$currentScene}\n{$extra}{$memoryPrompt}角色：{$charInfo}\n历史：\n{$context}\n玩家：{$playerInput}\n请按JSON格式输出：{\"new_scene\":\"场景变化({$sceneHint})\",\"speaker\":\"角色名\",\"reply\":\"【表情】内容\",{$optionsHint}} 只输出JSON。";

echo "=== Prompt (前300字符) ===\n";
echo mb_substr($prompt, 0, 300) . "...\n\n";

$data = [
    'model' => 'deepseek-chat',
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'temperature' => 0.8,
    'max_tokens' => 800
];

$ch = curl_init('https://api.deepseek.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";

if ($httpCode !== 200) {
    echo "API调用失败: " . mb_substr($response, 0, 500) . "\n";
    exit;
}

$result = json_decode($response, true);
if (empty($result['choices'][0]['message']['content'])) {
    echo "AI返回内容为空\n";
    print_r($result);
    exit;
}

$content = $result['choices'][0]['message']['content'];
echo "AI Raw Content (前500字符):\n";
echo mb_substr($content, 0, 500) . "\n\n";

preg_match('/\{.*\}/s', $content, $matches);
if (!$matches) {
    echo "✗ 无法匹配JSON结构\n";
    exit;
}

$parsed = json_decode($matches[0], true);
if ($parsed === null) {
    echo "✗ JSON解析失败，原文: " . $matches[0] . "\n";
    exit;
}

echo "✓ JSON解析成功\n";
print_r($parsed);
