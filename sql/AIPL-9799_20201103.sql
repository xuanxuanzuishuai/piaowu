CREATE TABLE `student_week_report` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT
  COMMENT '主键',
  `student_id`  int unsigned NOT NULL DEFAULT '0'
  COMMENT '学生ID',
  `year`        int unsigned NOT NULL
  COMMENT '年号',
  `week`        int unsigned NOT NULL DEFAULT '0'
  COMMENT '周号',
  `basic_info`  json         NOT NULL
  COMMENT '基本信息',
  `ai_comment`  varchar(255) NOT NULL DEFAULT ''
  COMMENT '评语',
  `progress`    json         NOT NULL
  COMMENT '进步曲目',
  `tasks`       json         NOT NULL
  COMMENT '周任务完成记录',
  `is_pass`     tinyint(1)   NOT NULL DEFAULT '0'
  COMMENT '是否及格:0未及格1及格',
  `create_time` int unsigned NOT NULL DEFAULT '0'
  COMMENT '创建时间',
  `update_time` int unsigned NOT NULL DEFAULT '0'
  COMMENT '修改时间',
  `start_time`  int unsigned NOT NULL
  COMMENT '数据统计开始时间',
  `end_time`    int unsigned NOT NULL
  COMMENT '数据统计结束时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_s_y_w` (`student_id`, `year`, `week`) USING BTREE
)
  COMMENT ='学生周报数据';


CREATE TABLE `holidays` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint unsigned NOT NULL DEFAULT '0'
   COMMENT '节日类型1除夕',
  `year` int unsigned NOT NULL DEFAULT '0'
   COMMENT '年号',
  `start_time` int unsigned NOT NULL DEFAULT '0'
   COMMENT '节日开始时间',
  `end_time` int unsigned NOT NULL DEFAULT '0'
   COMMENT '节日结束时间',
  PRIMARY KEY (`id`)
)COMMENT='中国假期时间对应表';


INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2021, 1612972800, 1613059199);
INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2022, 1643558400, 1643644799);
INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2023, 1674230400, 1674316799);
INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2024, 1707408000, 1707494399);
INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2025, 1737993600, 1738079999);
INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2026, 1771171200, 1771257599);
INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2027, 1801756800, 1801843199);
INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2028, 1832342400, 1832428799);
INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2029, 1865520000, 1865606399);
INSERT INTO `holidays`(`type`, `year`, `start_time`, `end_time`) VALUES (1, 2030, 1896192000, 1896278399);





INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'ai_comment_bad', '宝贝练琴天数、平均练琴时长、上课数、任务完成度和其他同学相比有差距，继续努力哦！', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'ai_comment_default', '宝贝本周没有练琴，记得使用小叶子练琴哦！', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'ai_comment_middle', '宝贝bad和其他同学相比有差距，good超越了其他同学，继续努力哦！', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'ai_comment_perfect', '宝贝全面超越了其他同学，继续保持哦！', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'compare_average_duration_name', '练琴时长', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'compare_class_name', '互动课堂', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'compare_play_days_name', '练琴天数', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'compare_task_name', '任务完成度', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'pass_line', '0.8', '及格线');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'tasks_total_count', '35', '周任务总数');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'diff_score', '5', '分差值');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'audio_limit', '3', '获取音频数据最大数量');

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES
('week_report_config', '练琴周报配置', 'rand_data', '{\"nor\":{\"days\":{\"min\":4.5,\"max\":5.5},\"duration\":{\"min\":35,\"max\":45},\"class\":{\"min\":0.85,\"max\":0.95},\"task\":{\"min\":75,\"max\":85}},\"va\":{\"days\":{\"min\":5,\"max\":6},\"duration\":{\"min\":45,\"max\":60},\"class\":{\"min\":0.85,\"max\":0.95},\"task\":{\"min\":78,\"max\":88}}}', NULL);


set @play_piano_30_minutes_task_id = (select id
                                      from erp_event_task
                                      where name = '本日累计练琴30分钟');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'play_piano_30_minutes', @play_piano_30_minutes_task_id, '练琴时长30分钟taskid');


set @difficult_points_task_id = (select id
                                 from erp_event_task
                                 where name = '学习1次重难点');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'difficult_points', @difficult_points_task_id, '学习重难点taskid');

set @evaluation_the_whole_song_task_id = (select id
                                          from erp_event_task
                                          where name = '完成1次双手全曲评测'  order by id asc limit 1);
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'evaluation_the_whole_song', @evaluation_the_whole_song_task_id, '全曲评测taskid');

set @sound_base_questions_task_id = (select id
                                     from erp_event_task
                                     where name = '完成1道音基题');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'sound_base_questions', @sound_base_questions_task_id, '音基题taskid');

set @watch_demo_video_task_id = (select id
                                 from erp_event_task
                                 where name = '看完1个示范视频');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', 'watch_demo_video', @watch_demo_video_task_id, '观看示范视频taskid');


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', @play_piano_30_minutes_task_id, '练琴时长', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', @evaluation_the_whole_song_task_id, '全曲评测', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', @sound_base_questions_task_id, '音基题', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', @watch_demo_video_task_id, '观看示范视频', '');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('week_report_config', '练琴周报配置', @difficult_points_task_id, '学习重难点', '');

-- 周报分享渠道
INSERT INTO `dict` ( `type`, `key_name`, `key_code`, `key_value`, `desc` )
VALUES
	( 'WEIXIN_STUDENT_CONFIG', '智能陪练微信端设置', 'week_report_share_channel_id', '2018', '周报分享渠道' );
-- 周报分享链接域名
INSERT INTO `dict` ( `type`, `key_name`, `key_code`, `key_value`, `desc` )
VALUES
  ( 'APP_CONFIG_STUDENT', 'AI练琴后端设置', 'week_report_share_assess_url', 'https://referral.xiaoyezi.com/market/index', '周报分享链接域名' );