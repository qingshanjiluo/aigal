CREATE DATABASE IF NOT EXISTS ai_galgame;
USE ai_galgame;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- 新增：角色立绘存储表
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

-- 新增：结局图鉴表
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
