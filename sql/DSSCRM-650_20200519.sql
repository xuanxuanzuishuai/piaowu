ALTER TABLE `privilege`
ADD COLUMN `status` TINYINT(2) NOT NULL DEFAULT 1 COMMENT '权限状态 0 不可用 1 正常' AFTER `unique_en_name`;
