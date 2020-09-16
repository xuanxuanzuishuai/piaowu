ALTER TABLE `gift_code`
ADD COLUMN `package_v1` TINYINT(2) NOT NULL DEFAULT '0' COMMENT '是否新产品包';

ALTER TABLE `third_part_bill`
ADD COLUMN `package_v1` TINYINT(2) NULL DEFAULT '0' COMMENT '1 新产品包 0 旧产品包' AFTER `parent_channel_id`;
