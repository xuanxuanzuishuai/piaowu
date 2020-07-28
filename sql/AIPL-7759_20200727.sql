ALTER TABLE `country_code` ADD COLUMN `hot` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '是否热门' AFTER `status`;

UPDATE `country_code` SET `status` = '1', `hot` = '1' WHERE (`id` = '71');
UPDATE `country_code` SET `status` = '1', `hot` = '1' WHERE (`id` = '99');
UPDATE `country_code` SET `status` = '1', `hot` = '1' WHERE (`id` = '166');
UPDATE `country_code` SET `status` = '1', `hot` = '1' WHERE (`id` = '179');
UPDATE `country_code` SET `status` = '1', `hot` = '1' WHERE (`id` = '12');
UPDATE `country_code` SET `hot` = '1' WHERE (`id` = '33');
UPDATE `country_code` SET `hot` = '1' WHERE (`id` = '38');
UPDATE `country_code` SET `hot` = '1' WHERE (`id` = '180');