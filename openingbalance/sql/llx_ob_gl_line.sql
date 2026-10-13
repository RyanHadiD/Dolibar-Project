CREATE TABLE llx_ob_gl_line (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_batch integer NOT NULL,
 account_number varchar(32) NOT NULL,
 debit decimal(24,8) NOT NULL DEFAULT 0,
 credit decimal(24,8) NOT NULL DEFAULT 0,
 note varchar(255) NOT NULL DEFAULT '',
 KEY idx_ob_gl_batch (entity,fk_batch)
) ENGINE=innodb;
