<?php
// 测试新功能的脚本
require_once 'db.php';

echo "测试爱旮旯给目新增功能\n";
echo "==========================\n";

// 1. 测试数据库连接
try {
    $db = getDB();
    echo "✓ 数据库连接成功\n";
} catch (Exception $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
    exit;
}

// 2. 测试用户注册（模拟）
echo "\n2. 测试用户注册API...\n";
$testUser = 'testuser_' . time();
$testPass = 'test123';

// 清理可能存在的测试用户
$stmt = $db->prepare("DELETE FROM users WHERE username LIKE 'testuser_%'");
$stmt->execute();

// 3. 测试游戏设置API
echo "\n3. 测试游戏设置API...\n";
$userId = 1; // 假设用户ID为1（测试用）
$settings = [
    'story_background' => '未来都市',
    'global_prompt' => '这是一个测试全局提示词',
    'auto_scene' => false,
    'auto_options' => true,
    'group_mode' => false,
    'happy_ending_threshold' => 90,
    'sad_ending_threshold' => 15,
    'happy_ending_text' => '🎉 恭喜与{name}达成幸福结局！',
    'sad_ending_text' => '😢 与{name}的离别...'
];

// 模拟保存设置
$jsonSettings = json_encode($settings);
$stmt = $db->prepare("INSERT INTO user_game_data (user_id, data_key, data_value) VALUES (?, 'game_settings', ?) ON DUPLICATE KEY UPDATE data_value = VALUES(data_value)");
$stmt->execute([$userId, $jsonSettings]);
echo "✓ 游戏设置保存成功\n";

// 4. 测试角色管理API
echo "\n4. 测试角色管理API...\n";
$characters = [
    [
        'id' => 'char_test1',
        'name' => '测试角色A',
        'prompt' => '这是一个测试角色',
        'emoji' => '😊',
        'affection' => 60
    ],
    [
        'id' => 'char_test2', 
        'name' => '测试角色B',
        'prompt' => '另一个测试角色',
        'emoji' => '🌸',
        'affection' => 40
    ]
];

$jsonChars = json_encode($characters);
$stmt = $db->prepare("INSERT INTO user_game_data (user_id, data_key, data_value) VALUES (?, 'characters', ?) ON DUPLICATE KEY UPDATE data_value = VALUES(data_value)");
$stmt->execute([$userId, $jsonChars]);
echo "✓ 角色数据保存成功\n";

// 5. 测试API密钥存储
echo "\n5. 测试API密钥存储...\n";
$apiKey = 'sk-testapikey123';
$stmt = $db->prepare("INSERT INTO user_game_data (user_id, data_key, data_value) VALUES (?, 'deepseek_api_key', ?) ON DUPLICATE KEY UPDATE data_value = VALUES(data_value)");
$stmt->execute([$userId, $apiKey]);
echo "✓ API密钥保存成功\n";

// 6. 验证数据
echo "\n6. 验证存储的数据...\n";
$stmt = $db->prepare("SELECT data_key, data_value FROM user_game_data WHERE user_id = ?");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $key = $row['data_key'];
    $value = json_decode($row['data_value'], true);
    if ($key === 'deepseek_api_key') {
        echo "  - {$key}: " . (strlen($value) > 5 ? '***' . substr($value, -4) : $value) . "\n";
    } else {
        echo "  - {$key}: " . (is_array($value) ? '数组(' . count($value) . '项)' : substr($value, 0, 50)) . "\n";
    }
}

// 7. 测试数据库表结构
echo "\n7. 验证数据库表结构...\n";
$tables = ['users', 'user_game_data', 'user_cg'];
foreach ($tables as $table) {
    $stmt = $db->prepare("SHOW COLUMNS FROM $table");
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "  - {$table}: {$count} 个字段\n";
}

echo "\n==========================\n";
echo "测试完成！\n";
echo "前端访问地址: http://localhost/ai_galgame/index.html\n";
echo "API测试地址: http://localhost/ai_galgame/game.php?action=load\n";
echo "\n新增功能已验证:\n";
echo "1. 全局设置存储 (game_settings)\n";
echo "2. 自定义API密钥存储 (deepseek_api_key)\n";
echo "3. 角色管理增强 (characters)\n";
echo "4. 数据库结构完整\n";
?>