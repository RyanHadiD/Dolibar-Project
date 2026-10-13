CREATE TABLE llx_pi_match (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_entry integer NOT NULL,
 fk_invoice integer NOT NULL,
 fk_invoice_line integer NOT NULL,
 qty decimal(24,8) NOT NULL,
 amount decimal(24,8) NOT NULL,
 fk_user integer NOT NULL,
 UNIQUE KEY uk_pi_match (entity,fk_entry,fk_invoice_line)
) ENGINE=innodb;
