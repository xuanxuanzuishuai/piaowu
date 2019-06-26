ALTER TABLE `student`
  ADD COLUMN `act_sub_info` INT(11) NOT NULL DEFAULT 0 COMMENT '用户操作，观看付费介绍次数' AFTER `trial_end_date`;

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'sub_info_count', '5');