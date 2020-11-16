INSERT INTO `dss_dev`.`dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('IP_WHITE_LIST', 'IP白名单', 'sales_master', '127.0.0.1,39.97.47.26', '鲸鱼跳跃回调鉴权');

CREATE TABLE `dss_dev`.`sales_master_customer_info` (
  `id` INT NOT NULL,
  `wechatNumber` VARCHAR(128) NULL,
  `creatTime` DATETIME NULL,
  `name` VARCHAR(64) NULL,
  `nickname` VARCHAR(64) NULL,
  `wechatMark` VARCHAR(64) NULL,
  `mobile` VARCHAR(16) NULL,
  `lastTimeFollow` DATETIME NULL,
  `salesStage` VARCHAR(32) NULL,
  `intention` VARCHAR(8) NULL,
  `source` VARCHAR(16) NULL,
  `effectiveCommunication` VARCHAR(8) NULL,
  `gender` VARCHAR(8) NULL,
  `birthday` DATETIME NULL,
  `comments` TEXT NULL,
  `student_id` INT NULL,
  PRIMARY KEY (`id`));


ALTER TABLE `dss_dev`.`student`
ADD COLUMN `wechatNumber` VARCHAR(128) NULL AFTER `allot_course_time`;
