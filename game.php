<?php
error_reporting(0);
ini_set('display_errors', '0');
require_once 'db.php';
header('Content-Type: application/json');

$userId = getCurrentUserId();
if (!$userId) {
    echo json_encode(['error' => '未登录']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function getApiKey() {
    $userId = getCurrentUserId();
    // 密钥不经过JSON编码，需要直接读取原始值
    $db = getDB();
    $stmt = $db->prepare("SELECT data_value FROM user_game_data WHERE user_id = ? AND data_key = 'deepseek_api_key'");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['data_value'])) {
        $val = $row['data_value'];
        // 兼容JSON编码和纯文本存储
        $decoded = json_decode($val, true);
        return ($decoded !== null && is_string($decoded)) ? $decoded : $val;
    }
    return DEEPSEEK_API_KEY;
}

function getUserSettings($userId) {
    $defaults = [
        'story_background' => '现代校园',
        'global_prompt' => '',
        'auto_scene' => true,
        'auto_options' => true,
        'group_mode' => false,
        'happy_ending_threshold' => 95,
        'sad_ending_threshold' => 10,
        'happy_ending_text' => '✨ 达成【幸福结局】与{name} ✨',
        'sad_ending_text' => '💔 达成【离别结局】'
    ];
    $saved = loadUserData($userId, 'game_settings');
    return array_merge($defaults, $saved ?: []);
}

// 获取角色表情映射
function getEmotionFromText($text) {
    $emotions = [
        'happy' => ['开心', '高兴', '笑', '喜', '乐', '愉快', '幸福', '满足'],
        'angry' => ['生气', '愤怒', '怒', '讨厌', '恨', '烦'],
        'sad' => ['难过', '伤心', '哭', '悲', '痛苦', '失落'],
        'shy' => ['害羞', '羞', '脸红', '不好意思'],
        'surprised' => ['惊讶', '惊', '吓一跳', '没想到', '震惊'],
        'normal' => []
    ];
    
    foreach ($emotions as $emotion => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return $emotion;
            }
        }
    }
    return 'normal';
}

if ($action === 'load') {
    $settings = getUserSettings($userId);
    $characters = loadUserData($userId, 'characters') ?? null;
    
    if (!$characters) {
        $characters = [
            [
                'id' => 'char_0', 
                'name' => '小爱', 
                'prompt' => '你是女主角小爱，活泼傲娇', 
                'emoji' => '😊', 
                'affection' => 50,
                'default_emotion' => 'normal',
                'current_emotion' => 'normal'
            ],
            [
                'id' => 'char_1', 
                'name' => '小雪', 
                'prompt' => '你是小雪，温柔内向', 
                'emoji' => '🌸', 
                'affection' => 50,
                'default_emotion' => 'normal',
                'current_emotion' => 'normal'
            ]
        ];
    }
    
    $affection = loadUserData($userId, 'affection') ?? [];
    if (empty($affection)) {
        foreach ($characters as $c) {
            $affection[$c['id']] = $c['affection'];
        }
    }
    
    // 获取角色立绘
    $portraits = [];
    $db = getDB();
    $stmt = $db->prepare("SELECT character_id, portrait_type, image_url FROM character_portraits WHERE user_id = ?");
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $portraits[$row['character_id']][$row['portrait_type']] = $row['image_url'];
    }
    
    $data = [
        'conversation_history' => loadUserData($userId, 'conversation_history') ?? [],
        'affection' => $affection,
        'current_scene' => loadUserData($userId, 'current_scene') ?? '现代校园',
        'characters' => $characters,
        'options' => loadUserData($userId, 'options') ?? [],
        'settings' => $settings,
        'has_custom_api_key' => !!loadUserData($userId, 'deepseek_api_key'),
        'portraits' => $portraits,
        'current_chapter' => loadUserData($userId, 'current_chapter') ?? 1,
        'chapter_title' => loadUserData($userId, 'chapter_title') ?? '第一章：初遇',
        'important_events' => getImportantEvents($userId),
        'auto_save_slot' => loadUserData($userId, 'auto_save_slot') ?? null
    ];
    
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true);
    saveUserData($userId, 'conversation_history', $input['conversation_history'] ?? []);
    saveUserData($userId, 'affection', $input['affection'] ?? []);
    saveUserData($userId, 'current_scene', $input['current_scene'] ?? '');
    saveUserData($userId, 'characters', $input['characters'] ?? []);
    saveUserData($userId, 'options', $input['options'] ?? []);
    saveUserData($userId, 'current_chapter', $input['current_chapter'] ?? 1);
    saveUserData($userId, 'chapter_title', $input['chapter_title'] ?? '');
    
    if (isset($input['auto_save_slot'])) {
        saveUserData($userId, 'auto_save_slot', $input['auto_save_slot']);
    }
    
    echo json_encode(['success' => true, 'message' => '存档成功']);
    exit;
}

if ($action === 'save_settings') {
    $input = json_decode(file_get_contents('php://input'), true);
    $settings = getUserSettings($userId);
    $allowed = ['story_background','global_prompt','auto_scene','auto_options','group_mode','happy_ending_threshold','sad_ending_threshold','happy_ending_text','sad_ending_text'];
    foreach ($allowed as $key) {
        if (isset($input[$key])) {
            if (in_array($key, ['happy_ending_threshold','sad_ending_threshold'])) {
                $settings[$key] = max(0, min(100, intval($input[$key])));
            } else {
                $settings[$key] = $input[$key];
            }
        }
    }
    saveUserData($userId, 'game_settings', $settings);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'save_api_key') {
    $input = json_decode(file_get_contents('php://input'), true);
    $key = trim($input['api_key'] ?? '');
    if ($key === '') {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM user_game_data WHERE user_id = ? AND data_key = 'deepseek_api_key'");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => '已恢复默认密钥']);
    } else {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO user_game_data (user_id, data_key, data_value) VALUES (?, 'deepseek_api_key', ?) ON DUPLICATE KEY UPDATE data_value = VALUES(data_value)");
        $stmt->execute([$userId, $key]);
        echo json_encode(['success' => true, 'message' => 'API密钥已保存']);
    }
    exit;
}

if ($action === 'get_api_key') {
    $db = getDB();
    $stmt = $db->prepare("SELECT data_value FROM user_game_data WHERE user_id = ? AND data_key = 'deepseek_api_key'");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['data_value'])) {
        $val = $row['data_value'];
        $decoded = json_decode($val, true);
        $actualKey = ($decoded !== null && is_string($decoded)) ? $decoded : $val;
        echo json_encode(['success' => true, 'has_key' => true, 'key_preview' => substr($actualKey, 0, 8) . '****' . substr($actualKey, -4)]);
    } else {
        echo json_encode(['success' => true, 'has_key' => false]);
    }
    exit;
}

if ($action === 'delete_character') {
    $input = json_decode(file_get_contents('php://input'), true);
    $charId = $input['char_id'] ?? '';
    if (!$charId) {
        echo json_encode(['error' => '无效角色']);
        exit;
    }
    $characters = loadUserData($userId, 'characters') ?? [];
    if (count($characters) <= 1) {
        echo json_encode(['error' => '至少保留一个角色']);
        exit;
    }
    $characters = array_values(array_filter($characters, function($c) use ($charId) { return $c['id'] !== $charId; }));
    $affection = loadUserData($userId, 'affection') ?? [];
    unset($affection[$charId]);
    saveUserData($userId, 'characters', $characters);
    saveUserData($userId, 'affection', $affection);
    echo json_encode(['success' => true, 'characters' => $characters, 'affection' => $affection]);
    exit;
}

if ($action === 'update_character') {
    $input = json_decode(file_get_contents('php://input'), true);
    $charId = $input['char_id'] ?? '';
    $updates = $input['updates'] ?? [];
    if (!$charId) {
        echo json_encode(['error' => '无效角色']);
        exit;
    }
    $characters = loadUserData($userId, 'characters') ?? [];
    $found = false;
    foreach ($characters as &$c) {
        if ($c['id'] === $charId) {
            if (isset($updates['name'])) $c['name'] = trim($updates['name']);
            if (isset($updates['prompt'])) $c['prompt'] = trim($updates['prompt']);
            if (isset($updates['affection'])) {
                $c['affection'] = max(0, min(100, intval($updates['affection'])));
            }
            if (isset($updates['default_emotion'])) $c['default_emotion'] = $updates['default_emotion'];
            if (isset($updates['current_emotion'])) $c['current_emotion'] = $updates['current_emotion'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo json_encode(['error' => '角色未找到']);
        exit;
    }
    if (isset($updates['affection'])) {
        $affection = loadUserData($userId, 'affection') ?? [];
        $affection[$charId] = $c['affection'];
        saveUserData($userId, 'affection', $affection);
    }
    saveUserData($userId, 'characters', $characters);
    echo json_encode(['success' => true, 'characters' => $characters]);
    exit;
}

// 新增：保存角色立绘
if ($action === 'save_portrait') {
    $input = json_decode(file_get_contents('php://input'), true);
    $characterId = $input['character_id'] ?? '';
    $portraitType = $input['portrait_type'] ?? 'normal';
    $imageUrl = $input['image_url'] ?? '';
    
    if (!$characterId || !$imageUrl) {
        echo json_encode(['error' => '参数不完整']);
        exit;
    }
    
    saveCharacterPortrait($userId, $characterId, $portraitType, $imageUrl);
    echo json_encode(['success' => true]);
    exit;
}

// 新增：获取角色立绘
if ($action === 'get_portraits') {
    $characterId = $_GET['character_id'] ?? null;
    $portraits = getCharacterPortraits($userId, $characterId);
    echo json_encode(['success' => true, 'portraits' => $portraits]);
    exit;
}

// 新增：记录重要事件
if ($action === 'record_event') {
    $input = json_decode(file_get_contents('php://input'), true);
    $eventKey = $input['event_key'] ?? '';
    $eventDescription = $input['event_description'] ?? '';
    
    if (!$eventKey || !$eventDescription) {
        echo json_encode(['error' => '参数不完整']);
        exit;
    }
    
    saveImportantEvent($userId, $eventKey, $eventDescription);
    echo json_encode(['success' => true]);
    exit;
}

// 新增：解锁结局
if ($action === 'unlock_ending') {
    $input = json_decode(file_get_contents('php://input'), true);
    $endingKey = $input['ending_key'] ?? '';
    $endingTitle = $input['ending_title'] ?? '';
    $endingDescription = $input['ending_description'] ?? '';
    $triggeredBy = $input['triggered_by'] ?? '';
    
    if (!$endingKey || !$endingTitle) {
        echo json_encode(['error' => '参数不完整']);
        exit;
    }
    
    unlockEnding($userId, $endingKey, $endingTitle, $endingDescription, $triggeredBy);
    echo json_encode(['success' => true]);
    exit;
}

// 新增：获取已解锁结局
if ($action === 'get_endings') {
    $endings = getUnlockedEndings($userId);
    echo json_encode(['success' => true, 'endings' => $endings]);
    exit;
}

if ($action === 'export_config') {
    $config = [
        'export_version' => '2.0',
        'export_time' => date('Y-m-d H:i:s'),
        'game_settings' => getUserSettings($userId),
        'conversation_history' => loadUserData($userId, 'conversation_history') ?? [],
        'affection' => loadUserData($userId, 'affection') ?? [],
        'current_scene' => loadUserData($userId, 'current_scene') ?? '现代校园',
        'current_chapter' => loadUserData($userId, 'current_chapter') ?? 1,
        'chapter_title' => loadUserData($userId, 'chapter_title') ?? '',
        'characters' => loadUserData($userId, 'characters') ?? [],
        'options' => loadUserData($userId, 'options') ?? [],
        'important_events' => getImportantEvents($userId)
    ];
    $filename = "galgame_config_" . date('Ymd_His') . ".json";
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'import_config') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['config'])) {
        echo json_encode(['error' => '无效配置数据']);
        exit;
    }
    $config = $input['config'];
    if (isset($config['game_settings'])) {
        saveUserData($userId, 'game_settings', $config['game_settings']);
    }
    if (isset($config['conversation_history'])) {
        saveUserData($userId, 'conversation_history', $config['conversation_history']);
    }
    if (isset($config['affection'])) {
        saveUserData($userId, 'affection', $config['affection']);
    }
    if (isset($config['current_scene'])) {
        saveUserData($userId, 'current_scene', $config['current_scene']);
    }
    if (isset($config['current_chapter'])) {
        saveUserData($userId, 'current_chapter', $config['current_chapter']);
    }
    if (isset($config['chapter_title'])) {
        saveUserData($userId, 'chapter_title', $config['chapter_title']);
    }
    if (isset($config['characters'])) {
        saveUserData($userId, 'characters', $config['characters']);
    }
    if (isset($config['options'])) {
        saveUserData($userId, 'options', $config['options']);
    }
    if (isset($config['important_events'])) {
        saveUserData($userId, 'important_events', $config['important_events']);
    }
    echo json_encode(['success' => true, 'message' => '配置已导入']);
    exit;
}

if ($action === 'chat') {
    $input = json_decode(file_get_contents('php://input'), true);
    $playerInput = $input['player_input'] ?? '';
    $history = $input['history'] ?? [];
    $characters = $input['characters'] ?? [];
    $affection = $input['affection'] ?? [];
    $currentScene = $input['current_scene'] ?? '现代校园';
    $currentChapter = $input['current_chapter'] ?? 1;
    $chapterTitle = $input['chapter_title'] ?? '第一章：初遇';
    $importantEvents = $input['important_events'] ?? [];
    $clientStoryBg = $input['story_background'] ?? '';
    $clientWorldPrompt = $input['world_prompt'] ?? '';
    $clientGlobalPrompt = $input['global_prompt'] ?? '';
    $settings = getUserSettings($userId);
    $groupMode = $settings['group_mode'] ?? false;

    if (empty($playerInput)) {
        echo json_encode(['error' => '输入不能为空']);
        exit;
    }

    $context = "";
    foreach (array_slice($history, -10) as $msg) {
        $context .= "{$msg['speaker']}: {$msg['text']}\n";
    }
    $charInfo = "";
    foreach ($characters as $c) {
        $aff = $affection[$c['id']] ?? 50;
        $charInfo .= "{$c['name']}(好感度{$aff}) ";
    }
    
    // 构建长期记忆提示
    $memoryPrompt = "";
    if (!empty($importantEvents)) {
        $memoryPrompt .= "重要记忆：\n";
        foreach ($importantEvents as $key => $event) {
            $memoryPrompt .= "- {$event['description']}\n";
        }
    }
    
    // 检测重要事件
    $detectedEvents = [];
    if (strpos($playerInput, '救') !== false) {
        $detectedEvents[] = ['key' => 'saved_'.time(), 'description' => '玩家曾在关键时刻救助了角色'];
    }
    if (strpos($playerInput, '秘密') !== false) {
        $detectedEvents[] = ['key' => 'secret_'.time(), 'description' => '角色向玩家透露了重要秘密'];
    }

    // 优先使用客户端传来的世界观设定（切换世界观时）
    $bg = $clientStoryBg ?: ($settings['story_background'] ?? '现代校园');
    $globalPrompt = $clientGlobalPrompt ?: ($settings['global_prompt'] ?? '');
    $worldPrompt = $clientWorldPrompt ?: ($settings['world_prompt'] ?? '');
    $autoScene = $settings['auto_scene'] ?? true;
    $autoOptions = $settings['auto_options'] ?? true;

    $extra = "";
    if ($worldPrompt) $extra .= "世界观设定：{$worldPrompt}\n";
    if ($globalPrompt) $extra .= "全局提示：{$globalPrompt}\n";
    if (!$autoScene) $extra .= "不要自动生成新场景，保持当前场景。\n";
    if (!$autoOptions) $extra .= "不要生成选项。\n";
    if ($currentChapter) $extra .= "当前章节：第{$currentChapter}章 {$chapterTitle}\n";

    if ($groupMode) {
        $prompt = "故事背景：{$bg}\n当前场景：{$currentScene}\n{$extra}{$memoryPrompt}角色：{$charInfo}\n对话历史：\n{$context}\n玩家最新说：{$playerInput}\n请生成2~3轮连续对话（不同角色依次发言），以JSON数组格式输出，每个元素包含\"speaker\"和\"reply\"，reply中包含【表情】。示例：[{\"speaker\":\"小爱\",\"reply\":\"【乐】真的吗？\"},{\"speaker\":\"小雪\",\"reply\":\"【喜】嗯！\"}] 只输出数组。";
    } else {
        $optionsHint = $autoOptions ? '包含"options":["选项1","选项2"]' : '不要生成options字段';
        $sceneHint = $autoScene ? 'new_scene可为空表示不变' : 'new_scene必须为空';
        $prompt = "故事背景：{$bg}\n当前场景：{$currentScene}\n{$extra}{$memoryPrompt}角色：{$charInfo}\n历史：\n{$context}\n玩家：{$playerInput}\n请按JSON格式输出：{\"new_scene\":\"场景变化({$sceneHint})\",\"speaker\":\"角色名\",\"reply\":\"【表情】内容\",{$optionsHint}} 只输出JSON。如果玩家输入包含重要事件（如救人、泄露秘密），请在response中包含\"important_event\":\"事件描述\"。";
    }

    $data = [
        'model' => 'deepseek-chat',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.8,
        'max_tokens' => 800
    ];

    $apiKey = getApiKey();
    $ch = curl_init(DEEPSEEK_API_URL);
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
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        echo json_encode(['error' => '网络连接失败 (cURL错误)', 'detail' => $curlError]);
        exit;
    }

    if ($httpCode !== 200) {
        echo json_encode(['error' => 'AI接口调用失败 (HTTP ' . $httpCode . ')', 'detail' => mb_substr($response, 0, 500)]);
        exit;
    }

    $result = json_decode($response, true);
    if (empty($result['choices'][0]['message']['content'])) {
        echo json_encode(['error' => 'AI返回内容为空或格式异常', 'detail' => $response]);
        exit;
    }
    $content = $result['choices'][0]['message']['content'];

    preg_match('/\{.*\}/s', $content, $matches);
    if (!$matches && $groupMode) {
        preg_match('/\[.*\]/s', $content, $matches);
    }
    if (!$matches) {
        echo json_encode(['error' => 'AI返回格式错误', 'raw' => $content]);
        exit;
    }

    $parsed = json_decode($matches[0], true);
    if ($parsed === null) {
        echo json_encode(['error' => 'JSON解析失败', 'raw' => $matches[0]]);
        exit;
    }
    
    // 添加检测到的重要事件
    if (!empty($detectedEvents)) {
        $parsed['detected_events'] = $detectedEvents;
    }
    
    // 添加情绪分析
    if (isset($parsed['reply'])) {
        $parsed['emotion'] = getEmotionFromText($parsed['reply']);
    }

    echo json_encode(['success' => true, 'data' => $parsed]);
    exit;
}

if ($action === 'export_story') {
    $history = loadUserData($userId, 'conversation_history') ?? [];
    $currentChapter = loadUserData($userId, 'current_chapter') ?? 1;
    $chapterTitle = loadUserData($userId, 'chapter_title') ?? '第一章：初遇';
    
    $txt = "【爱旮旯给目】游戏故事导出\n";
    $txt .= "时间：" . date('Y-m-d H:i:s') . "\n";
    $txt .= "章节：第{$currentChapter}章 {$chapterTitle}\n\n";
    
    foreach ($history as $msg) {
        $txt .= "[{$msg['speaker']}] {$msg['text']}\n";
    }
    $filename = "galgame_story_" . date('Ymd_His') . ".txt";
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $txt;
    exit;
}

// 导出为HTML文字游戏
if ($action === 'export_html_game') {
    $history = loadUserData($userId, 'conversation_history') ?? [];
    $chars = loadUserData($userId, 'characters') ?? [];
    $settings = getUserSettings($userId);
    $bg = $settings['story_background'] ?? '现代校园';
    $chapter = loadUserData($userId, 'current_chapter') ?? 1;
    $chapterTitle = loadUserData($userId, 'chapter_title') ?? '第一章：初遇';
    
    $charStyles = '';
    $charMap = [];
    $emojis = ['😊', '😄', '😠', '😢', '😳', '😲'];
    $colors = ['#ff9a9e', '#a1c4fd', '#fbc2eb', '#84fab0', '#e0c3fc', '#ffecd2'];
    foreach ($chars as $i => $c) {
        $color = $colors[$i % count($colors)];
        $charStyles .= ".msg-{$c['id']} { background: linear-gradient(135deg, {$color} 0%, #fff 100%); border-left: 4px solid {$color}; }\n";
        $charMap[$c['name']] = ['id' => $c['id'], 'emoji' => $c['emoji'] ?? $emojis[$i % count($emojis)]];
    }
    
    $msgs = '';
    foreach ($history as $msg) {
        if ($msg['speaker'] === '系统') {
            $msgs .= "<div class='scene-change'>{$msg['text']}</div>\n";
            continue;
        }
        $isPlayer = $msg['speaker'] === '玩家';
        $cls = $isPlayer ? 'msg-player' : 'msg-char';
        if (!$isPlayer && isset($charMap[$msg['speaker']])) {
            $cls .= ' msg-' . $charMap[$msg['speaker']]['id'];
            $avatar = $charMap[$msg['speaker']]['emoji'];
        } else {
            $avatar = $isPlayer ? '🧑' : '😊';
        }
        $msgs .= "<div class='{$cls}'><span class='avatar'>{$avatar}</span><span class='name'>{$msg['speaker']}</span><div class='bubble'>" . htmlspecialchars($msg['text']) . "</div></div>\n";
    }
    
    $html = "<!DOCTYPE html><html lang='zh-CN'><head><meta charset='UTF-8'><title>爱旮旯给目 - {$bg}物语</title><style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Microsoft YaHei',sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        .container{max-width:700px;margin:0 auto;background:rgba(255,255,255,0.95);border-radius:20px;padding:30px;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
        h1{text-align:center;color:#764ba2;margin-bottom:10px}
        .meta{text-align:center;color:#888;font-size:14px;margin-bottom:20px}
        .scene-change{text-align:center;color:#667eea;font-size:13px;margin:15px 0;padding:8px;background:rgba(102,126,234,0.1);border-radius:8px}
        .msg-char,.msg-player{display:flex;align-items:flex-start;margin:12px 0;gap:10px}
        .msg-player{flex-direction:row-reverse}
        .avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;background:rgba(255,255,255,0.8);box-shadow:0 2px 8px rgba(0,0,0,0.1)}
        .name{font-size:12px;color:#888;margin-bottom:2px}
        .bubble{padding:12px 16px;border-radius:16px;max-width:500px;font-size:15px;line-height:1.6;box-shadow:0 2px 8px rgba(0,0,0,0.08)}
        .msg-char .bubble{background:#f8f9fa;border-bottom-left-radius:4px}
        .msg-player .bubble{background:#667eea;color:#fff;border-bottom-right-radius:4px}
        " . $charStyles . "
        .footer{text-align:center;margin-top:30px;padding-top:20px;border-top:1px solid #eee;color:#aaa;font-size:12px}
    </style></head><body>
    <div class='container'>
        <h1>🎮 爱旮旯给目</h1>
        <div class='meta'>{$bg} · 第{$chapter}章 {$chapterTitle} · 导出时间：" . date('Y-m-d H:i:s') . "</div>
        <div class='story'>
            " . $msgs . "
        </div>
        <div class='footer'>由 爱旮旯给目 自动生成 · 仅供个人收藏</div>
    </div>
    </body></html>";
    
    $filename = "galgame_html_" . date('Ymd_His') . ".html";
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $html;
    exit;
}

if ($action === 'tts') {
    $text = $_GET['text'] ?? '';
    $speaker = $_GET['speaker'] ?? '小爱';
    if (empty($text)) {
        echo json_encode(['error' => '文本为空']);
        exit;
    }
    $url = "https://api.milorapart.top/apis/AIvoice/?text=" . urlencode($text) . "&speaker=" . urlencode($speaker);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode == 200) {
        $data = json_decode($resp, true);
        if (isset($data['code']) && $data['code'] == 200 && !empty($data['url'])) {
            echo json_encode(['success' => true, 'audio_url' => $data['url']]);
        } else {
            echo json_encode(['error' => 'TTS 生成失败', 'detail' => $data]);
        }
    } else {
        echo json_encode(['error' => 'TTS 服务不可用']);
    }
    exit;
}

if ($action === 'unlock_cg') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cgKey = $input['cg_key'] ?? '';
    $desc = $input['description'] ?? '';
    if ($cgKey) {
        $db = getDB();
        $stmt = $db->prepare("INSERT IGNORE INTO user_cg (user_id, cg_key, cg_description) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $cgKey, $desc]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => '无效CG']);
    }
    exit;
}

if ($action === 'get_cg_gallery') {
    $db = getDB();
    $stmt = $db->prepare("SELECT cg_key, cg_description, unlocked_at FROM user_cg WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cgList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'list' => $cgList]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => '无效操作']);
?>
