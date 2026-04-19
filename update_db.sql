USE ai_galgame;

CREATE TABLE IF NOT EXISTS character_portraits (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  character_id varchar(50) NOT NULL,
  portrait_type varchar(20) NOT NULL,
  image_url text NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY user_char_type (user_id, character_id, portrait_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_endings (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  ending_key varchar(50) NOT NULL,
  ending_title varchar(100) NOT NULL,
  ending_description text,
  triggered_by varchar(50) DEFAULT NULL,
  unlocked_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY user_ending (user_id, ending_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
