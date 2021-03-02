-- EPR:!!!
-- EPR:!!!
-- EPR:!!!
INSERT INTO `erp_event_task` (`type`, `condition`, `event_id`, `name`, `desc`, `create_time`, `creator_id`, `update_time`, `updator_id`, `award`, `start_time`, `end_time`, `status`)
VALUES
	(13, '{\"total_days\":\"1\"}', 33, '打卡1次', '9.9', unix_timestamp(), 10972, unix_timestamp(), 10104, '{\"awards\":[{\"to\":2,\"amount\":990,\"type\":1,\"need_check\":1,\"delay\":0}]}', 1607788800, 1735574400, 1),
	(13, '{\"total_days\":\"2\"}', 33, '打卡2次', '6.9', unix_timestamp(), 10972, unix_timestamp(), 10972, '{\"awards\":[{\"to\":2,\"amount\":690,\"type\":1,\"need_check\":1,\"delay\":0}]}', 1607788800, 1735574400, 1),
	(13, '{\"total_days\":\"3\"}', 33, '打卡3次', '3', unix_timestamp(), 10972, unix_timestamp(), 10104, '{\"awards\":[{\"to\":2,\"amount\":300,\"type\":1,\"need_check\":1,\"delay\":0}]}', 1607788800, 1735574400, 1);





-- OP:!!!
-- OP:!!!
-- OP:!!!
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'task_ids', '[332,333,334]', '参加打卡活动班级关联的EVENT ID');

-- ('share_poster_check_reason', '分享截图审核原因', '1', '分享分组可见', NULL),
-- ('share_poster_check_reason', '分享截图审核原因', '2', '未使用最新海报', NULL),
-- ('share_poster_check_reason', '分享截图审核原因', '3', '上传截图出错', NULL),
-- ('share_poster_check_reason', '分享截图审核原因', '4', '分享无分享语', NULL),
-- ('share_poster_check_reason', '分享截图审核原因', '6', '朋友圈保留时长不足', NULL)


UPDATE `dict` SET `key_value` = '不可设置私密或分组可见，请重新分享' WHERE `key_code` = '1' AND `type` = 'share_poster_check_reason';
UPDATE `dict` SET `key_value` = '请上传分享朋友圈的截图' WHERE `key_code` = '2' AND `type` = 'share_poster_check_reason';
UPDATE `dict` SET `key_value` = '请按照示例，截图朋友圈，再上传截图 ' WHERE `key_code` = '3' AND `type` = 'share_poster_check_reason';

-- REDIS :
-- del dict_list_share_poster_check_reason