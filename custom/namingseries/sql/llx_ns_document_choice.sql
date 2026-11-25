CREATE TABLE llx_ns_document_choice (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  document_type varchar(32) NOT NULL,
  fk_object integer NOT NULL,
  fk_series integer NOT NULL,
  fk_user integer DEFAULT NULL,
  datec datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ns_document_choice (entity, document_type, fk_object),
  KEY idx_ns_document_choice_series (entity, fk_series)
) ENGINE=innodb;
