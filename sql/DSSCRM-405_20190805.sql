-- 添加字段
ALTER TABLE `schedule_extend` ADD (`audio_comment` VARCHAR(4096) DEFAULT NULL COMMENT '课后语音评价字段');
ALTER TABLE `schedule_extend` ADD COLUMN `audio_duration` INT(11) NULL DEFAULT '0' COMMENT '语音评价时长';
ALTER TABLE `homework_task` ADD (`homework_audio` VARCHAR(4096) DEFAULT NULL COMMENT '作业的语音字段');
ALTER TABLE `homework_task` ADD COLUMN `audio_duration` INT(11) NULL DEFAULT '0' COMMENT '语音时长';
