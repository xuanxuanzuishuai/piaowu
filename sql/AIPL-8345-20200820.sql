INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'get_lesson_rank_time_offset_20202', '45705600', '2020年第2季度时间偏移量');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'get_lesson_rank_time_offset_20203', '3283199', '2020年第3季度时间偏移量');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'get_lesson_rank_time_standard', '1598198400', '2020年第3季度基准时间点');


CREATE TABLE `history_ranks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `issue_number` int unsigned NOT NULL COMMENT '期号',
  `lesson_id` int unsigned NOT NULL COMMENT '课程ID',
  `student_id` int unsigned NOT NULL COMMENT '学生ID',
  `ai_record_id` int unsigned NOT NULL COMMENT '记录ID',
  `score` float unsigned NOT NULL COMMENT '分数',
  `play_id` int unsigned NOT NULL COMMENT 'ai_play_record表ID',
  `create_time` int unsigned NOT NULL COMMENT '创建时间',
  `type` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '排行榜类型:1季度排名',
  PRIMARY KEY (`id`),
  KEY `idx_t_num_lesson_s` (`type`,`issue_number`,`lesson_id`,`score`) USING BTREE
) COMMENT='学生演奏历史排行榜';