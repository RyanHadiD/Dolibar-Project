CREATE TABLE llx_pi_document_action (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 element_type varchar(32) NOT NULL,
 fk_element integer NOT NULL,
 element_ref varchar(128) NOT NULL DEFAULT '',
 action_code varchar(32) NOT NULL,
 reason varchar(1000) NOT NULL,
 snapshot_json mediumtext,
 fk_user integer NOT NULL,
 date_action datetime NOT NULL,
 KEY idx_pi_doc_action (entity,element_type,fk_element),
 KEY idx_pi_doc_action_date (entity,date_action)
) ENGINE=innodb;
