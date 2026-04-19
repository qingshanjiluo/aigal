<?php
// InfinityFree 远程数据库配置
define('DB_HOST', 'sql208.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_41698662_aigal'); // 实际创建的数据库名
define('DB_USER', 'if0_41698662');
define('DB_PASS', 'C5vHkefWVPMq8sZ');

define('DEEPSEEK_API_KEY', 'sk-your-deepseek-api-key-here');
define('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions');

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();
?>