
ALTER TABLE `ai_play_record`
ADD COLUMN `data_type` TINYINT(2) NOT NULL DEFAULT '1' COMMENT '1 正常评测 2 未进行测评数据 3 非正常退出数据';