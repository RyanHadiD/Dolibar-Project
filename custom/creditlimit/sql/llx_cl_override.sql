CREATE TABLE llx_cl_override (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 element_type varchar(32) NOT NULL,
 fk_element integer NOT NULL,
 element_ref varchar(128) NOT NULL DEFAULT '',
 fk_soc integer NOT NULL,
 document_amount double(24,8) NOT NULL DEFAULT 0,
 reason varchar(1000) NOT NULL,
 snapshot_json mediumtext NOT NULL,
 status varchar(16) NOT NULL DEFAULT 'approved',
 fk_user_approve integer NOT NULL,
 date_approve datetime NOT NULL,
 date_expiry datetime NOT NULL,
 fk_user_use integer DEFAULT NULL,
 date_use datetime DEFAULT NULL,
 KEY idx_cl_override_doc (entity,element_type,fk_element,status),
 KEY idx_cl_override_soc (entity,fk_soc,date_approve)
) ENGINE=innodb;
