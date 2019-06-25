-- 添加字段
ALTER TABLE `play_record` ADD (`ai_type` TINYINT DEFAULT NULL);
-- 更新数据，如果是小程序则是语音识别，否则为演奏
UPDATE `play_record` SET `ai_type` = IF(`client_type` = 3, 2, 1);