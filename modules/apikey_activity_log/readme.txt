CREATE TABLE api_key_copy_log (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(10) UNSIGNED NOT NULL,
  group_name VARCHAR(255) NOT NULL,
  api_key_value VARCHAR(255) NOT NULL,
  activity VARCHAR(255) NOT NULL,
  copied_at INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_uid (uid),
  KEY idx_copied_at (copied_at),
  KEY idx_group_name (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

