CREATE TABLE llx_pi_stock_entry_line (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 fk_stock_entry integer NOT NULL,
 rang integer NOT NULL DEFAULT 0,
 fk_product integer NOT NULL DEFAULT 0,
 fk_warehouse integer NOT NULL DEFAULT 0,
 fk_target_warehouse integer NOT NULL DEFAULT 0,
 qty decimal(24,8) NOT NULL,
 unit_cost decimal(24,8),
 difference_account varchar(32) NOT NULL DEFAULT '',
 fk_source_entry integer NOT NULL DEFAULT 0,
 fk_pi_entry integer NOT NULL DEFAULT 0,
 fk_pi_entry_target integer NOT NULL DEFAULT 0,
 note varchar(255) NOT NULL DEFAULT '',
 KEY idx_pi_stock_entry_line_parent (fk_stock_entry,rang),
 KEY idx_pi_stock_entry_line_ledger (fk_pi_entry)
) ENGINE=innodb;
