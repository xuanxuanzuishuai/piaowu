CREATE TABLE `push_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `jump_type` tinyint(4) NOT NULL COMMENT '1:首页，2:webView，3:浏览器链接，4:小程序，5:音符商城，6:练琴日历，7:套课详情',
  `push_content_android` text  NOT NULL COMMENT '推动到安卓设备的内容',
  `push_content_ios` text  NOT NULL COMMENT '推动到ios设备的内容',
  `remark` varchar(255)  NOT NULL DEFAULT '' COMMENT '推送备注',
  `push_id_android` varchar(36) NOT NULL DEFAULT '' COMMENT '安卓推送ID',
  `push_id_ios` varchar(36)  NOT NULL DEFAULT '' COMMENT 'ios推送ID',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) COMMENT='推送消息记录表';


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc` )
VALUES
	( 'SERVICE_CONFIG', '外部服务设置', 'tpns_host', 'https://api.tpns.tencent.com', 'tpnsHost' ),
	( 'SERVICE_CONFIG', '外部服务设置', 'access_id_android', '1500015188', 'access_id_android'),
	( 'SERVICE_CONFIG', '外部服务设置', 'secret_key_android', '7f6c559aff7e7cf6d1a4ba7ff2d1c4c8', 'secret_key_android'),
	( 'SERVICE_CONFIG', '外部服务设置', 'access_id_ios', '1600015187', 'access_id_ios'),
	( 'SERVICE_CONFIG', '外部服务设置', 'secret_key_ios', '03092fc009ca84d7a2b78ee88b9787d2', 'secret_key_ios'),
	( 'ORG_WEB_CONFIG', '后台配置', 'push_user_template', 'prod/excel/push_user_template.xlsx', 'push模板下载');