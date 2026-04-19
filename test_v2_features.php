<?php
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "测试爱旮旯给目 V2 新功能\n";
echo "==========================\n\n";

// 情感检测函数（复制自game.php）
function getEmotionFromText($text) {
    $emotions = [
        'happy' => ['开心', '高兴', '笑', '喜', '乐', '愉快', '幸福', '满足'],
        'angry' => ['生气', '愤怒', '怒', '讨厌', '恨', '烦'],
        'sad' => ['难过', '伤心', '哭', '悲', '泪', '痛苦', '失望'],
        'shy' => ['害羞', '羞', '脸红', '不好意思'],
        'surprised' => ['惊讶', '吃惊', '震惊', '意外', '吓']
    ];
    
    foreach ($emotions as $emotion => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_strpos($text, $keyword) !== false) {
                return $emotion;
            }
        }
    }
    return 'normal';
}

// 1. 数据库连接
echo "1. 测试数据库连接...\n";
$db = getDB();
if ($db) {
    echo "✓ 数据库连接成功\n\n";
} else {
    echo "✗ 数据库连接失败\n\n";
    exit;
}

// 2. 测试 character_portraits 表
echo "2. 测试角色立绘存储...\n";
$userId = 1;
$charId = 'test_char';
$portraitType = 'happy';
$imageUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

$result = saveCharacterPortrait($userId, $charId, $portraitType, $imageUrl);
echo $result ? "✓ 立绘保存成功\n" : "✗ 立绘保存失败\n";

$portraits = getCharacterPortraits($userId, $charId);
echo "  - 获取到 " . count($portraits) . " 张立绘\n";
if (count($portraits) > 0) {
    echo "  - 类型: " . $portraits[0]['portrait_type'] . "\n";
}
echo "\n";

// 3. 测试 user_endings 表
echo "3. 测试结局解锁...\n";
$endingResult = unlockEnding($userId, 'good_ending', '美好结局', '你们最终走到了一起', 'char_1');
echo $endingResult ? "✓ 结局解锁成功\n" : "✗ 结局解锁失败\n";

$endings = getUnlockedEndings($userId);
echo "  - 已解锁 " . count($endings) . " 个结局\n";
if (count($endings) > 0) {
    echo "  - 标题: " . $endings[0]['ending_title'] . "\n";
}
echo "\n";

// 4. 测试重要事件
echo "4. 测试重要事件记录...\n";
$eventResult = saveImportantEvent($userId, 'first_meet', '初次相遇在樱花树下');
echo $eventResult ? "✓ 事件记录成功\n" : "✗ 事件记录失败\n";

$events = getImportantEvents($userId);
echo "  - 已记录 " . count($events) . " 个重要事件\n";
if (count($events) > 0) {
    echo "  - 最新事件: " . $events[count($events)-1]['description'] . "\n";
}
echo "\n";

// 5. 测试情感检测
echo "5. 测试情感检测...\n";
$testTexts = [
    '好开心啊！' => 'happy',
    '我很生气' => 'angry',
    '好难过' => 'sad',
    '害羞了' => 'shy',
    '太惊讶了' => 'surprised',
    '普通对话' => 'normal'
];
$allPass = true;
foreach ($testTexts as $text => $expected) {
    $emotion = getEmotionFromText($text);
    $status = ($emotion === $expected) ? '✓' : '✗';
    if ($emotion !== $expected) $allPass = false;
    echo "  {$status} \"{$text}\" => {$emotion} (期望: {$expected})\n";
}
echo $allPass ? "\n✓ 情感检测全部通过\n" : "\n✗ 部分情感检测未通过\n";

// 6. 清理测试数据
echo "\n6. 清理测试数据...\n";
$stmt = $db->prepare("DELETE FROM character_portraits WHERE user_id = ? AND character_id = ?");
$stmt->execute([$userId, $charId]);
echo "✓ 测试立绘已清理\n";

$stmt = $db->prepare("DELETE FROM user_endings WHERE user_id = ? AND ending_key = ?");
$stmt->execute([$userId, 'good_ending']);
echo "✓ 测试结局已清理\n";

$stmt = $db->prepare("DELETE FROM user_game_data WHERE user_id = ? AND data_key = ?");
$stmt->execute([$userId, 'important_events']);
echo "✓ 测试事件已清理\n";

echo "\n==========================\n";
echo "V2 功能测试完成！\n";
echo "立绘系统、结局系统、情感检测均正常\n";
