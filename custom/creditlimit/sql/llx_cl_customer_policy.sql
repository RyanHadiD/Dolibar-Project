CREATE TABLE llx_cl_customer_policy (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_soc integer NOT NULL,
 control_mode varchar(16) NOT NULL DEFAULT 'inherit',
 max_overdue_days integer DEFAULT NULL,
 overdue_amount_limit double(24,8) DEFAULT NULL,
 grace_days integer DEFAULT NULL,
 credit_hold tinyint NOT NULL DEFAULT 0,
 note varchar(1000) DEFAULT NULL,
 fk_user_modif integer,
 datec datetime NOT NULL,
 tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_cl_customer_policy (entity,fk_soc),
 KEY idx_cl_customer_policy_soc (fk_soc)
) ENGINE=innodb;
