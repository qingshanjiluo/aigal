<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_POST['action'] === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (strlen($username) < 3 || strlen($password) < 4) {
        echo json_encode(['success' => false, 'message' => '用户名至少3位，密码至少4位']);
        exit;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '用户名已存在']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    if ($stmt->execute([$username, $hash])) {
        echo json_encode(['success' => true, 'message' => '注册成功，请登录']);
    } else {
        echo json_encode(['success' => false, 'message' => '注册失败']);
    }
    exit;
}

if ($_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $db = getDB();
    $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        echo json_encode(['success' => true, 'message' => '登录成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '用户名或密码错误']);
    }
    exit;
}

if ($_GET['action'] === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => '已登出']);
    exit;
}

if ($_GET['action'] === 'me') {
    if (getCurrentUserId()) {
        echo json_encode(['success' => true, 'username' => $_SESSION['username']]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => '无效请求']);
?>