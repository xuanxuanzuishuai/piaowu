INSERT INTO `dict` ( `type`, `key_name`, `key_code`, `key_value`, `desc` )
VALUES
('wechat_app_id', '微信配置', '8_12', 'wx55bb3af4852bcb00', '上音社合作-小叶子AI智能陪练小程序appid' ),
('wechat_app_secret', '微信配置', '8_12', 'f0f743f9804aaa7856b91706fc2e245b', '上音社合作-小叶子AI智能陪练小程序secret' ),
('WEB_PROMOTION_CONFIG', 'web活动配置', 'allowed_channel', '[4129]', '允许领取的渠道'),
('REFERRAL_CONFIG', '转介绍配置', 'allowed_0_channel', '[4081,4080]', '指定0元的渠道'),
('AI_PLAY_MINI_APP_CONFIG', '小叶子AI智能陪练小程序', 'verify_switch', '0', '是否提审状态'),


CREATE TABLE `free_code_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `user_uuid` varchar(32) NOT NULL DEFAULT '' COMMENT '用户UUID',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB;