INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('award_status', '奖励状态', '0', '未达标', ''),
('award_status', '奖励状态', '1', '待领取', ''),
('award_status', '奖励状态', '2', '已领取', '');

SET @pid = ( SELECT id FROM privilege WHERE NAME = "计数任务" LIMIT 1);
INSERT INTO `privilege` ( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ('计数任务-参与列表', '/op_web/activity_sign/list', unix_timestamp(), 'get', 1, '参与列表', @pid, 'activity_sign_list', 1),
  ('计数任务-参与详情', '/op_web/activity_sign/user_list', unix_timestamp(), 'get', 0, '参与详情', @pid, 'activity_sign_user_list', 1),

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc` )
VALUES
( 'ORG_WEB_CONFIG', '后台配置', 'export_amount', 1000, '列表导出轮询数量'),
( 'ORG_WEB_CONFIG', '后台配置', 'export_total', 100000, '列表导出总数量'),