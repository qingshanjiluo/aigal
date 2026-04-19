-- ============================================
-- 爱旮旯给目 (AI Galgame) - InfinityFree 清空重建脚本
-- 先删除旧表，再创建新表
-- 适用于免费主机（无 CREATE DATABASE / USE）
-- ============================================

-- 禁用外键检查，避免删除顺序问题
SET FOREIGN_KEY_CHECKS = 0;

-- 删除旧表（如果存在）
DROP TABLE IF EXISTS `user_endings`;
DROP TABLE IF EXISTS `character_portraits`;
DROP TABLE IF EXISTS `user_cg`;
DROP TABLE IF EXISTS `user_game_data`;
DROP TABLE IF EXISTS `users`;

-- 恢复外键检查
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 创建新表
-- ============================================

-- 用户表
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户游戏数据表（键值对存储）
CREATE TABLE `user_game_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `data_key` varchar(100) NOT NULL,
  `data_value` text NOT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_key` (`user_id`,`data_key`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CG 图鉴解锁表
CREATE TABLE `user_cg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `cg_key` varchar(50) NOT NULL,
  `cg_description` varchar(255) DEFAULT NULL,
  `unlocked_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_cg` (`user_id`,`cg_key`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 角色立绘存储表
CREATE TABLE `character_portraits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `character_id` varchar(50) NOT NULL,
  `portrait_type` varchar(20) NOT NULL COMMENT 'normal, happy, angry, sad, shy, surprised, closeup, side',
  `image_url` text NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_char_type` (`user_id`,`character_id`,`portrait_type`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 结局图鉴表
CREATE TABLE `user_endings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ending_key` varchar(50) NOT NULL,
  `ending_title` varchar(100) NOT NULL,
  `ending_description` text,
  `unlocked_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `triggered_by` varchar(50) DEFAULT NULL COMMENT '角色ID或事件',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_ending` (`user_id`,`ending_key`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 插入默认数据
-- ============================================

-- 默认管理员账号 (密码: admin123，请登录后立即修改)
INSERT INTO `users` (`id`, `username`, `password_hash`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
