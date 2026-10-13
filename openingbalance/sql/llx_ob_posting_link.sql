CREATE TABLE llx_ob_posting_link (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_batch integer NOT NULL,
 line_type varchar(16) NOT NULL,
 fk_line integer NOT NULL,
 object_type varchar(32) NOT NULL,
 object_id integer NOT NULL,
 datec datetime NOT NULL,
 UNIQUE KEY uk_ob_posting_link (entity,fk_batch,line_type,fk_line,object_type,object_id),
 KEY idx_ob_posting_batch (entity,fk_batch)
) ENGINE=innodb;
