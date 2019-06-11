ALTER TABLE `play_record` ADD COLUMN `client_type` TINYINT(4) NULL COMMENT '演奏来源 1爱学琴APP 2智能琴房APP 3熊猫小程序' AFTER `lesson_type`;
UPDATE `play_record` SET `client_type` = 1 WHERE `schedule_id` IS NULL;
UPDATE `play_record` SET `client_type` = 2 WHERE `schedule_id` = 0;