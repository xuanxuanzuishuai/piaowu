ALTER TABLE `gift_code`
  ADD COLUMN `bill_app_id` TINYINT NULL DEFAULT NULL COMMENT '订单app_id' AFTER `bill_amount`;