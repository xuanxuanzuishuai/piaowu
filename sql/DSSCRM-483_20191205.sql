ALTER TABLE `flags`
ADD COLUMN `is_opposite` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '反转判断条件' AFTER `is_opposite`;