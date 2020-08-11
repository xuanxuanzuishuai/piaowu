-- 上线的时候需要和erp确认
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`)
VALUES ('credit_activity_config', '积分活动', 'music_basic_question_task_id', '43'),
('credit_activity_config', '积分活动', 'example_video_task_id', '44'),
('credit_activity_config', '积分活动', 'view_difficult_spot_task_id', '45'),
('credit_activity_config', '积分活动', 'know_chart_promotion_task_id', '46')

ALTER TABLE `erp_event_task`
ADD COLUMN `order_num` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;