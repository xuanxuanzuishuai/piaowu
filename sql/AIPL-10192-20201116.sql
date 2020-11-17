INSERT INTO `erp_dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('event_type', '事件类型', '11', '新手任务');
INSERT INTO `erp_dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('event_task_type', '任务类型', '14', '新手任务');
INSERT INTO `erp_dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('event_task_type', '任务类型', '15', '新手任务额外奖励');

INSERT INTO `erp_goods_v1`
(`id`, `name`, `num`, `free_num`, `status`, `create_time`, `update_time`, `creator_id`, `updator_id`, `desc`, `category_id`, `thumbs`, `extension`, `is_show`, `is_custom`)
VALUES
('211', '新手任务', 0, 0, 1, 1596626234, 1596626239, 10837, 10837, '{\"desc\": \"新手任务额外奖章\"}', 17, '[\"prod/medal/xsrwb4d8f802088056038464e54cf49.jpeg\"]', '{\"parent_id\": \"0\", \"medal_type\": \"1\"}', 0, 0);


INSERT INTO
`erp_goods_v1`(`id`, `name`, `num`, `free_num`, `status`, `create_time`, `update_time`, `creator_id`, `updator_id`, `desc`, `category_id`, `thumbs`, `extension`, `is_show`, `is_custom`)
VALUES
('212', '新手任务奖章', 0, 0, 1, 1596626234, 1596626239, 10837, 10837, '{\"desc\": \"\"}', 17, '[\"prod/medal/xsrwb4d8f802088056038464e54cf49.jpeg\"]', '{\"parent_id\": \"211\", \"medal_type\": \"1\"}', 0, 0);


ALTER TABLE `student`
ADD COLUMN `start_play_piano_time` INT(11) NULL DEFAULT NULL COMMENT '开始练琴时间' AFTER `real_name`,
ADD COLUMN `class_return_time` VARCHAR(20) NULL DEFAULT NULL COMMENT '回课时间（周一至周日）' AFTER `start_play_piano_time`;

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'novice_activity_video', '/dev/noviceActivity/xsrw.mp4', '新手任务提升视频');

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('novice_activity_config', '新手任务配置', 'novice_activity_type', '14', '新手任务正常任务类型');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('novice_activity_config', '新手任务配置', 'novice_activity_additional_type', '15', '新手任务额外奖励类型');
--------------------------------------------------------上线时，先执行分割线以上sql，分割线以下需要在erp后台创建完task，再执行---------------------------------------------------------------------------
select @id:=id from `erp_event` where `name` = '新手任务';
SELECT @task_id:=group_concat(id) from `erp_event_task` where event_id = @id;
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('novice_activity_config', '新手任务配置', 'novice_activity_task', @task_id, '所有的task_id');

