CREATE TABLE `wechat_poster` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT '图片名称',
  `url` text NOT NULL COMMENT '图片url 支持单图，多图可以用json格式记录',
  `apply_type` tinyint(2) unsigned NOT NULL DEFAULT '1' COMMENT '图片应用终端类型:1学生服务号 2老师服务号',
  `desc` text COMMENT '图片描述',
  `status` tinyint(2) unsigned NOT NULL DEFAULT '1' COMMENT '状态1未发布 2已发布 ',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间戳',
  `creator_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '创建人id',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后更新时间戳',
  `updator_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '最后更新人id',
  `settings` text NOT NULL COMMENT '图片设置 json格式。可以记录海报中微信二维码，或是个性图片的位置设置。',
  `content1` text COMMENT '海报文档1',
  `content2` text COMMENT '海报文档2',
  `poster_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '海报类型:1微信转介绍标准海报 2微信转介绍自定义个性化海报',
  PRIMARY KEY (`id`)
) COMMENT='微信海报数据配置表';

CREATE TABLE `wechat_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '公众号类型1学生端',
  `content` text NOT NULL COMMENT '数据内容',
  `msg_type` varchar(10) NOT NULL DEFAULT 'event' COMMENT 'text关键字回复event消息推送事件',
  `content_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '内容类型1文本消息 2图片消息',
  `event_type` varchar(45) NOT NULL COMMENT '推送事件类型subscribe关注公众号unsubscribe取消关注',
  `create_time` int(11) unsigned NOT NULL COMMENT '数据创建时间',
  `update_time` int(11) unsigned NOT NULL COMMENT '数据修改时间',
  `create_uid` int(11) unsigned NOT NULL COMMENT '创建人uid',
  `update_uid` int(11) unsigned NOT NULL COMMENT '修改人uid',
  PRIMARY KEY (`id`)
) COMMENT='微信公众号关注等事件配置表';

-- dict map
INSERT INTO `dict` ( `type`, `key_name`, `key_code`, `key_value`, `desc` ) VALUES ('wechat_type', '微信公众号类型', '1', '学生服务号', '' );
INSERT INTO `dict` ( `type`, `key_name`, `key_code`, `key_value`, `desc` ) VALUES ('wechat_type', '微信公众号类型', '2', '老师服务号', '' );
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('publish_status', '转介绍图片发布状态', '1', '未发布', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('publish_status', '转介绍图片发布状态', '2', '已发布', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('poster_type', '海报类型', '1', '微信转介绍标准海报', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('poster_type', '海报类型', '2', '微信转介绍自定义个性化海报', NULL);


-- 权限
INSERT INTO `privilege`(`id`, `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES (468, '海报详情', '/org_web/poster/detail', 1572315610, 'get', 0, '', 0, 'detail_poster');
INSERT INTO `privilege`(`id`, `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES (467, '编辑海报', '/org_web/poster/modify', 1572315610, 'post', 0, '', 0, 'edit_poster');
INSERT INTO `privilege`(`id`, `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES (466, '添加海报', '/org_web/poster/add', 1572315610, 'post', 0, '', 0, 'add_poster');
INSERT INTO `privilege`(`id`, `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES (465, '海报管理', '/org_web/poster/list', 1572315610, 'get', 1, '海报管理', 464, 'poster_list');
INSERT INTO `privilege`(`id`, `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES (464, '运营管理', NULL, 1543215441, 'get', 1, '运营管理', 0, 'operations_management');