<?php
require_once 'config.php';

function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $port = defined('DB_PORT') ? DB_PORT : '3306';
            $dsn = "mysql:host=".DB_HOST.";port=".$port.";dbname=".DB_NAME.";charset=utf8mb4";
            $db = new PDO($dsn, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die(json_encode(['error' => '数据库连接失败']));
        }
    }
    return $db;
}

function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
}

function saveUserData($userId, $key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO user_game_data (user_id, data_key, data_value) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE data_value = VALUES(data_value)");
    return $stmt->execute([$userId, $key, json_encode($value)]);
}

function loadUserData($userId, $key) {
    $db = getDB();
    $stmt = $db->prepare("SELECT data_value FROM user_game_data WHERE user_id = ? AND data_key = ?");
    $stmt->execute([$userId, $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return json_decode($row['data_value'], true);
    }
    return null;
}

// 新增：保存角色立绘
function saveCharacterPortrait($userId, $characterId, $portraitType, $imageUrl) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO character_portraits (user_id, character_id, portrait_type, image_url) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE image_url = VALUES(image_url)");
    return $stmt->execute([$userId, $characterId, $portraitType, $imageUrl]);
}

// 新增：获取角色立绘
function getCharacterPortraits($userId, $characterId = null) {
    $db = getDB();
    if ($characterId) {
        $stmt = $db->prepare("SELECT portrait_type, image_url FROM character_portraits WHERE user_id = ? AND character_id = ?");
        $stmt->execute([$userId, $characterId]);
    } else {
        $stmt = $db->prepare("SELECT character_id, portrait_type, image_url FROM character_portraits WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 新增：解锁结局
function unlockEnding($userId, $endingKey, $endingTitle, $endingDescription = null, $triggeredBy = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO user_endings (user_id, ending_key, ending_title, ending_description, triggered_by) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $endingKey, $endingTitle, $endingDescription, $triggeredBy]);
}

// 新增：获取已解锁结局
function getUnlockedEndings($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT ending_key, ending_title, ending_description, unlocked_at, triggered_by FROM user_endings WHERE user_id = ? ORDER BY unlocked_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 新增：获取长期记忆（重要事件）
function saveImportantEvent($userId, $eventKey, $eventDescription) {
    $events = loadUserData($userId, 'important_events') ?: [];
    $events[$eventKey] = [
        'description' => $eventDescription,
        'timestamp' => time()
    ];
    return saveUserData($userId, 'important_events', $events);
}

// 新增：获取所有重要事件
function getImportantEvents($userId) {
    return loadUserData($userId, 'important_events') ?: [];
}
?>
