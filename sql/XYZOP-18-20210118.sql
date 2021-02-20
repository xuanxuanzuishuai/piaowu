INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_app_id', '微信配置', '21_9', 'wxe88362427d492922', '代理小程序APPID');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_app_secret', '微信配置', '21_9', 'efd76942b966aafe6782403ec45a5e2b', '代理小程序SECRET');
-- PROD:
-- INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_app_id', '微信配置', '21_9', '', '代理小程序APPID');
-- INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_app_secret', '微信配置', '21_9', '0ceed93905c1a6dafc2ff3b6932ad0b8', '代理小程序SECRET');

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('AGENT_CONFIG', '代理系统设置', 'channel_distribution', '3095', '代理分销渠道'),
('AGENT_CONFIG', '代理系统设置', 'channel_individual', '3096', '代理个人渠道'),
('AGENT_CONFIG', '代理系统设置', 'channel_offline', '3097', '代理线下渠道'),
('AGENT_CONFIG', '代理系统设置', 'channel_dict', '{"1":3095, "2":3096, "3":3097}', '代理类型渠道字典'),
('AGENT_CONFIG', '代理系统设置', 'default_thumb', 'prod/thumb/default_thumb_agent.png', '代理默认头像');

CREATE TABLE `user_weixin` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  `user_type` int(1) NOT NULL DEFAULT '0' COMMENT '用户类型:4代理',
  `open_id` varchar(32) NOT NULL DEFAULT '' COMMENT '微信open_id',
  `union_id` varchar(32) DEFAULT '' COMMENT '微信union_id',
  `status` int(1) NOT NULL DEFAULT '1' COMMENT '状态:1有效;2无效;',
  `busi_type` int(3) NOT NULL DEFAULT '0' COMMENT '业务类型 9:代理小程序',
  `app_id` int(3) NOT NULL DEFAULT '0' COMMENT '应用id',
  `thumb` varchar(256) NOT NULL DEFAULT '' COMMENT '微信头像',
  `nickname` varchar(20) NOT NULL DEFAULT '' COMMENT '微信昵称',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `user_openid` (`open_id`)
) ENGINE=InnoDB COMMENT='用户微信表';

CREATE TABLE `goods_resource` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `package_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '代理账户ID',
  `ext` json NOT NULL COMMENT '详细配置字段',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_package_id` (`package_id`)
) ENGINE=InnoDB COMMENT='商品资源表';

CREATE TABLE `agent_application` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(12) NOT NULL DEFAULT '' COMMENT '名称',
  `mobile` varchar(16) NOT NULL DEFAULT '' COMMENT '手机号',
  `country_code` int(10) unsigned NOT NULL DEFAULT '86' COMMENT '手机国家区号',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='代理申请数据表';

CREATE TABLE `agent_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `agent_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '代理账户ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `bind_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '绑定操作时间',
  `deadline` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '绑定截止时间',
  `stage` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '进度:0注册;1体验;2年卡;',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `agent_user_bind` (`agent_id`,`user_id`)
) ENGINE=InnoDB COMMENT='代理用户绑定表';
