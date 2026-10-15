CREATE TABLE llx_cl_config (
 entity integer NOT NULL,
 enabled tinyint NOT NULL DEFAULT 1,
 default_limit double(24,8) NOT NULL DEFAULT 0,
 default_mode varchar(16) NOT NULL DEFAULT 'block',
 max_overdue_days integer NOT NULL DEFAULT 60,
 overdue_amount_limit double(24,8) NOT NULL DEFAULT 0,
 grace_days integer NOT NULL DEFAULT 0,
 check_order tinyint NOT NULL DEFAULT 1,
 check_shipment tinyint NOT NULL DEFAULT 1,
 check_invoice tinyint NOT NULL DEFAULT 1,
 include_orders tinyint NOT NULL DEFAULT 1,
 override_valid_days integer NOT NULL DEFAULT 7,
 fk_user_modif integer,
 tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (entity)
) ENGINE=innodb;
