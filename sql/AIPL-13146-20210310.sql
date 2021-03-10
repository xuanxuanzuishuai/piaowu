INSERT INTO `dict` ( `type`, `key_name`, `key_code`, `key_value`, `desc` )
VALUES
	('wechat_app_id', '微信配置', '8_10', 'wx6aaca64ad27efaeb', '测评分享小程序APPID' ),
	('wechat_app_secret', '微信配置', '8_10', '04ba387bb049523a95cf7c4716f1b6d6', '测评分享小程序secret' );


-- 清除缓存 DB=11

del dict_list_wechat_app_id
del dict_list_wechat_app_secret
