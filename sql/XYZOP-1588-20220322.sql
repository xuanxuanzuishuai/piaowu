ALTER TABLE `bill_map`
ADD COLUMN `open_id` varchar(32) NOT NULL DEFAULT '' COMMENT '下单人的open_id,不是所有单子都有' AFTER `type`,
ADD INDEX `idx_open_id`(`open_id`) USING BTREE;