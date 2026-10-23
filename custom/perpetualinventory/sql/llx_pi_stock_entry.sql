CREATE TABLE llx_pi_stock_entry (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 ref varchar(64) NOT NULL,
 purpose varchar(32) NOT NULL,
 date_post datetime NOT NULL,
 status smallint NOT NULL DEFAULT 0,
 note varchar(255) NOT NULL DEFAULT '',
 request_key varchar(32) NOT NULL,
 fk_user_author integer NOT NULL,
 fk_user_valid integer,
 datec datetime NOT NULL,
 date_valid datetime,
 tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_pi_stock_entry_ref (entity,ref),
 UNIQUE KEY uk_pi_stock_entry_request (entity,request_key),
 KEY idx_pi_stock_entry_status (entity,status,date_post)
) ENGINE=innodb;
