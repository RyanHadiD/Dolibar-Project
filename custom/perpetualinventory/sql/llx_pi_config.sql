CREATE TABLE llx_pi_config (
 entity integer NOT NULL PRIMARY KEY,
 enabled integer NOT NULL DEFAULT 0,
 stock_account varchar(32) NOT NULL DEFAULT '',
 grni_account varchar(32) NOT NULL DEFAULT '',
 cogs_account varchar(32) NOT NULL DEFAULT '',
 adjustment_account varchar(32) NOT NULL DEFAULT '',
 opening_account varchar(32) NOT NULL DEFAULT '',
 journal_code varchar(32) NOT NULL DEFAULT '',
 start_date datetime,
 lock_date date,
 fk_user integer NOT NULL DEFAULT 0
) ENGINE=innodb;
