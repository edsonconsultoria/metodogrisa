CREATE TABLE IF NOT EXISTS auth_remember_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(12) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  user_agent_hash CHAR(64) NOT NULL,
  last_used_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  `inclusao` datetime DEFAULT current_timestamp(),
  `alteracao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_selector (selector),
  KEY idx_user_id (user_id),
  KEY idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
