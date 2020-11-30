INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('SERVICE_CONFIG', '外部服务设置', 'dss_host', 'http://dss-dev.xiaoyezi.com', 'dss中心host');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_app_id', '微信的app_id', '8_1', 'wxce03ba2689898fe2', '智能陪练服务号');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_app_id', '微信的app_id', '8_8', 'wx57c6f675638b170f', '智能陪练转介绍小程序');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_app_secret', '微信的app_id', '8_1', 'cf37ac9729815b5be60b72c4843173c3', '智能陪练服务号');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_app_secret', '微信的app_id', '8_8', 'cb89cbadc4fa2ed5072f86501565359d', '智能陪练转介绍小程序');

insert into student_invite (id, student_id,referee_id,referee_type,create_time,referee_empoyee_id,activity_id,app_id)
select id,student_id,referee_id,referee_type,create_time,NULL,NULL,8
from dss_dev.student_referee;