CREATE TABLE llx_pi_balance (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_product integer NOT NULL,
 fk_warehouse integer NOT NULL,
 qty decimal(24,8) NOT NULL DEFAULT 0,
 stock_value decimal(24,8) NOT NULL DEFAULT 0,
 last_date datetime,
 UNIQUE KEY uk_pi_balance (entity,fk_product,fk_warehouse)
) ENGINE=innodb;
