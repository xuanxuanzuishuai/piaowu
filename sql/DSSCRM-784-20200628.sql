CREATE TABLE `wx_tags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` tinyint(1) unsigned NOT NULL DEFAULT '8' COMMENT '应用id:1真人 8dss智能',
  `busi_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '公众号类型:1学生端 2教师端',
  `name` varchar(255) NOT NULL COMMENT '标签名称',
  `weixin_tag_id` int(11) unsigned NOT NULL COMMENT '微信标签id',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态:0无效 1有效',
  `create_uid` int(11) unsigned NOT NULL COMMENT '创建人uid',
  `update_uid` int(11) unsigned NOT NULL COMMENT '修改人uid',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `idx_wtag_id` (`weixin_tag_id`) USING BTREE COMMENT '微信标签ID普通索引'
) COMMENT='微信用户标签';


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('tag_status', '标签状态', '0', '无效', NULL);
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('tag_status', '标签状态', '1', '有效', NULL);


INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES ('微信标签删除', '/org_web/wx_tag/del', 1593334648, 'post', 0, '', 0, 'wx_tag_del', 1);
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES ('微信标签修改', '/org_web/wx_tag/update', 1593334648, 'post', 0, '', 0, 'wx_tag_update', 1);
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES ('微信标签添加', '/org_web/wx_tag/add', 1593334648, 'post', 0, '', 0, 'wx_tag_add', 1);
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES ('微信标签列表', '/org_web/wx_tag/list', 1593334648, 'get', 1, '标签列表', 435, 'wx_tag_list', 1);