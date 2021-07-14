INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('student_task_default', '任务中心默认banner', 'banner', 'xxxxx', '默认banner路径');
  ('student_task_default', '消息配置ID', 'message_config_id', 280, '消息配置ID');

INSERT INTO `wechat_config`(`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`, `event_task_id`, `to`) VALUES
(1, '奖励金叶子已经发放，详情如下：\n任务名称：{{name}}\n任务内容：{{instruction}}\n完成情况：已完成，奖励{{awardValue}}金叶子已发放，请到【我的账户】及时查看\n<a href=\"{{url}}\">【点此消息】查看金叶子明细</a>', '3', 1, 'award', '', unix_timestamp(), 0, 0, 0, 0, 1);