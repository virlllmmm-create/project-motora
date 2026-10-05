-- Additive and repeatable community chat migration. Existing accounts and
-- communities are preserved. Run against the existing MOTORA database.
ALTER TABLE communities
  ADD COLUMN IF NOT EXISTS send_messages ENUM('all','admins') NOT NULL DEFAULT 'all',
  ADD COLUMN IF NOT EXISTS edit_info ENUM('all','admins') NOT NULL DEFAULT 'admins',
  ADD COLUMN IF NOT EXISTS add_members ENUM('all','admins') NOT NULL DEFAULT 'admins',
  ADD COLUMN IF NOT EXISTS pin_messages ENUM('all','admins') NOT NULL DEFAULT 'admins',
  ADD COLUMN IF NOT EXISTS start_calls ENUM('all','admins') NOT NULL DEFAULT 'all',
  ADD COLUMN IF NOT EXISTS invite_token CHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_community_invite ON communities(invite_token);
ALTER TABLE community_members
  ADD COLUMN IF NOT EXISTS role ENUM('leader','admin','member') NOT NULL DEFAULT 'member',
  ADD COLUMN IF NOT EXISTS last_read_message_id INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS muted TINYINT(1) NOT NULL DEFAULT 0;
INSERT INTO community_members(community_id,user_id,status,role)
  SELECT id,owner_id,'approved','leader' FROM communities
  ON DUPLICATE KEY UPDATE status='approved',role='leader';
UPDATE community_members cm JOIN communities c ON c.id=cm.community_id
  SET cm.role='member' WHERE cm.role='leader' AND cm.user_id<>c.owner_id;
CREATE TABLE IF NOT EXISTS community_messages (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,sender_id INT NULL,body TEXT NOT NULL,
  kind ENUM('message','system') NOT NULL DEFAULT 'message',
  attachment VARCHAR(255) NULL,reply_to INT NULL,
  edited_at DATETIME NULL,deleted_at DATETIME NULL,deleted_by INT NULL,
  pinned_until DATETIME NULL,pinned_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_group_messages(community_id,id),
  CONSTRAINT fk_group_message_community FOREIGN KEY(community_id) REFERENCES communities(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_message_sender FOREIGN KEY(sender_id) REFERENCES `user`(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS community_message_reactions (
  message_id INT NOT NULL,user_id INT NOT NULL,emoji VARCHAR(16) NOT NULL,
  PRIMARY KEY(message_id,user_id),
  CONSTRAINT fk_group_reaction_message FOREIGN KEY(message_id) REFERENCES community_messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_reaction_user FOREIGN KEY(user_id) REFERENCES `user`(id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS community_message_hidden (
  message_id INT NOT NULL,user_id INT NOT NULL,PRIMARY KEY(message_id,user_id),
  CONSTRAINT fk_group_hidden_message FOREIGN KEY(message_id) REFERENCES community_messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_hidden_user FOREIGN KEY(user_id) REFERENCES `user`(id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS community_calls (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,community_id INT NOT NULL,
  started_by INT NOT NULL,kind ENUM('audio','video') NOT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,ended_at DATETIME NULL,
  KEY idx_group_calls(community_id,ended_at),
  CONSTRAINT fk_group_call_community FOREIGN KEY(community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS community_call_participants (
  call_id INT NOT NULL,user_id INT NOT NULL,session_token CHAR(32) NOT NULL,
  last_seen DATETIME NOT NULL,PRIMARY KEY(call_id,user_id),
  CONSTRAINT fk_group_call_participant FOREIGN KEY(call_id) REFERENCES community_calls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS community_call_signals (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,call_id INT NOT NULL,
  sender_id INT NOT NULL,recipient_id INT NOT NULL,
  sender_session CHAR(32) NOT NULL,recipient_session CHAR(32) NOT NULL,
  payload MEDIUMTEXT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_group_signal(call_id,recipient_id,id),
  CONSTRAINT fk_group_signal_call FOREIGN KEY(call_id) REFERENCES community_calls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
