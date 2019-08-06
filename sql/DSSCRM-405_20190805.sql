-- 添加字段
ALTER TABLE `schedule_extend` ADD (`audio_comment` VARCHAR(4096) DEFAULT NULL);
ALTER TABLE `homework_task` ADD (`homework_audio` VARCHAR(4096) DEFAULT NULL);