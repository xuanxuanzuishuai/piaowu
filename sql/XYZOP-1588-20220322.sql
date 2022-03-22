ALTER TABLE `bill_map`
ADD COLUMN `open_id` varchar(32) NOT NULL DEFAULT '' COMMENT '下单人的open_id,不是所有单子都有' AFTER `type`,
ADD COLUMN `is_success` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否支付成功' AFTER `open_id`,
ADD INDEX `idx_open_id`(`open_id`) USING BTREE;