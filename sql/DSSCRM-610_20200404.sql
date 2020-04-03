ALTER TABLE `student`
ADD COLUMN `sync_status` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否已导入到真人:1未操作2已操作' AFTER `last_remark_id`;

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('system_env', '系统设置', 'NSQ_TOPIC_PREFIX', '', '消息队列topic前缀');
update `dict` set `key_value`='172.17.211.46:4161' where `type`='system_env' and `key_code`='NSQ_LOOKUPS';

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name` )
VALUES ('导入真人业务', '/org_web/student/syncDataToCrm', 1586398181, 'post', 0, '导入真人业务', 0, 'syncDataToCrm' );