ALTER TABLE `gift_code`
  CHANGE COLUMN `bill_id` `bill_id` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '子订单id' ,
  CHANGE COLUMN `bill_amount` `bill_amount` INT(11) NOT NULL DEFAULT 0 COMMENT '订单金额' ,
  CHANGE COLUMN `bill_app_id` `bill_app_id` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '订单app_id' ,
  ADD COLUMN `parent_bill_id` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '主订单id 支付查单用主单id，退款用子单id' AFTER `bill_id`,
  ADD INDEX `parent_bill_id` (`parent_bill_id` ASC),
  ADD INDEX `bill_id` (`bill_id` ASC);