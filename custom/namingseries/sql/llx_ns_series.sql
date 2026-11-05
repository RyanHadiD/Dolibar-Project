CREATE TABLE llx_ns_series (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  code varchar(64) NOT NULL,
  label varchar(128) NOT NULL,
  document_type varchar(32) NOT NULL DEFAULT 'sales_order',
  pattern varchar(255) NOT NULL,
  reset_period varchar(16) NOT NULL DEFAULT 'monthly',
  start_value integer NOT NULL DEFAULT 1,
  is_default smallint NOT NULL DEFAULT 0,
  active smallint NOT NULL DEFAULT 1,
  datec datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat integer DEFAULT NULL,
  fk_user_modif integer DEFAULT NULL,
  UNIQUE KEY uk_ns_series_code (entity, document_type, code),
  KEY idx_ns_series_lookup (entity, document_type, active, is_default)
) ENGINE=innodb;
