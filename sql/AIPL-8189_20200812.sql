ALTER TABLE `student`
ADD COLUMN `is_join_ranking` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否加入排行榜 0未启用排行榜 1启用排行榜 2禁用排行榜' AFTER `password`;

ALTER TABLE `ai_play_record`
ADD COLUMN `is_join_ranking` TINYINT(4) NULL DEFAULT 1 COMMENT '是否加入排行榜 0未启用排行榜 1启用排行榜 2禁用排行榜' AFTER `score_rank`;

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'get_lesson_rank_time', '3', '获取当前季度的排行榜');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'get_lesson_rank_time_offset', '432000', '时间偏移量');

