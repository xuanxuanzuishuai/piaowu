INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_APP_NAME', '产品线名', '1', '真人陪练');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_APP_NAME', '产品线名', '8', '智能陪练');

# has_used 是之前的废弃字段 换成新字段 上线所有学生默认为8
ALTER TABLE `student`
CHANGE COLUMN `has_used` `serve_app_id` TINYINT NULL DEFAULT 8 COMMENT '售后服务归属方 1 真人 8 智能' ;
UPDATE `student` SET `serve_app_id` = 8;