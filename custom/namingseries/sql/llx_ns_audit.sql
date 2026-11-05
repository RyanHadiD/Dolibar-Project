CREATE TABLE llx_ns_audit (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_series integer DEFAULT NULL,
  event_type varchar(32) NOT NULL,
  scope_key varchar(64) DEFAULT NULL,
  old_value text DEFAULT NULL,
  new_value text DEFAULT NULL,
  document_type varchar(32) DEFAULT NULL,
  document_id integer DEFAULT NULL,
  document_ref varchar(255) DEFAULT NULL,
  fk_user integer DEFAULT NULL,
  datec datetime NOT NULL,
  KEY idx_ns_audit_entity_date (entity, datec),
  KEY idx_ns_audit_series (fk_series)
) ENGINE=innodb;
