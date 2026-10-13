CREATE TABLE llx_ob_stock_line (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_batch integer NOT NULL,
 fk_product integer NOT NULL,
 fk_warehouse integer NOT NULL,
 qty decimal(24,8) NOT NULL,
 unit_cost decimal(24,8) NOT NULL,
 note varchar(255) NOT NULL DEFAULT '',
 UNIQUE KEY uk_ob_stock_line (entity,fk_batch,fk_product,fk_warehouse),
 KEY idx_ob_stock_batch (entity,fk_batch)
) ENGINE=innodb;
