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
