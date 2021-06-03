-- //上传截图奖励规则分割时间点
-- NORMAL_UPLOAD_POSTER_TASK
-- NORMAL_UPLOAD_POSTER_DIVISION_TIME
-- const NORMAL_UPLOAD_POSTER_DIVISION_TIME = [
--     'type' => 'normal_poster_change_award',
--     'keys' => [
--         'division_time'
--     ]
-- ];

ALTER TABLE `share_poster` ADD `points_award_id` VARCHAR (128) NOT NULL DEFAULT '' COMMENT "积分奖励id" AFTER `ext`;

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES
 ('normal_poster_change_award', '日常上传截图奖励改变时间分割点', 'division_time', '1612713600', '日常上传截图奖励规则改变时间分割点'),
 ('normal_upload_poster_task', '日常上传截图奖励task_id', '-1', '478', '第一次奖励10'),
 ('normal_upload_poster_task', '日常上传截图奖励task_id', '0', '476', '第一次奖励30'),
 ('normal_upload_poster_task', '日常上传截图奖励task_id', '1', '477', '第二次奖励20'),
 ('normal_upload_poster_task', '日常上传截图奖励task_id', '2', '477', '第三次奖励20');


INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_poster_check_reason', '分享截图审核原因', '7', '二维码或日期标签未展现完整', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_poster_check_reason', '分享截图审核原因', '8', '无法看到是否屏蔽，请直接在朋友圈内截图', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_poster_check_reason', '分享截图审核原因', '9', '无法看到是否屏蔽，请截取完整图片', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_poster_check_reason', '分享截图审核原因', '10', '请选用有日期标签和二维码的最新海报原图', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_poster_check_reason', '分享截图审核原因', '11', '请发布到朋友圈并截取朋友圈图片', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_poster_check_reason', '分享截图审核原因', '12', '朋友圈保留时长不足12小时', NULL);

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('normal_poster_change_award', '日常上传截图奖励改变时间分割点', 'division_time', '1612108800', '日常上传截图奖励规则改变时间分割点');


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('normal_upload_poster_task', '日常上传截图奖励task_id', '-1', '478', '第一次奖励10'),
('normal_upload_poster_task', '日常上传截图奖励task_id', '0', '476', '第一次奖励30'),
('normal_upload_poster_task', '日常上传截图奖励task_id', '1', '477', '第二次奖励20'),
('normal_upload_poster_task', '日常上传截图奖励task_id', '2', '477', '第三次奖励20');


INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('REFERRAL_CONFIG', '转介绍配置', 'refused_poster_url', '/operation/activity/awards/recordList?awtype=week', '海报审核未通过跳转地址'),
('REFERRAL_CONFIG', '转介绍配置', 'week_activity_url', '/operation/activity/awards/index', '周周有奖活动首页地址');

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('STUDENT_INVITE', '学生转介绍渠道', 'CHANNEL_STANDARD_POSTER', '3419', '标准海报渠道'),