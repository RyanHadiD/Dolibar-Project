CREATE TABLE llx_ob_party_line (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_batch integer NOT NULL,
 kind varchar(8) NOT NULL,
 fk_soc integer NOT NULL,
 source_ref varchar(128) NOT NULL,
 invoice_date date NOT NULL,
 due_date date NOT NULL,
 original_amount decimal(24,8) NOT NULL,
 prior_paid decimal(24,8) NOT NULL DEFAULT 0,
 outstanding decimal(24,8) NOT NULL,
 currency varchar(3) NOT NULL,
 note varchar(255) NOT NULL DEFAULT '',
 created_object_id integer,
 UNIQUE KEY uk_ob_party_line (entity,fk_batch,kind,fk_soc,source_ref),
 KEY idx_ob_party_batch (entity,fk_batch,kind)
) ENGINE=innodb;
