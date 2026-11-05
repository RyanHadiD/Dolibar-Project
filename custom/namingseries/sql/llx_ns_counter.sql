CREATE TABLE llx_ns_counter (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_series integer NOT NULL,
  scope_key varchar(64) NOT NULL,
  current_value integer NOT NULL DEFAULT 0,
  datec datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ns_counter_scope (entity, fk_series, scope_key),
  KEY idx_ns_counter_series (fk_series)
) ENGINE=innodb;
