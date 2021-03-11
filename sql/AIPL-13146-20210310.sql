INSERT INTO `dict` ( `type`, `key_name`, `key_code`, `key_value`, `desc` )
VALUES
	('wechat_app_id', '微信配置', '8_10', 'wx6aaca64ad27efaeb', '测评分享小程序APPID' ),
	('wechat_app_secret', '微信配置', '8_10', '04ba387bb049523a95cf7c4716f1b6d6', '测评分享小程序secret' ),
	('wechat_app_push_config', '微信消息配置', '8_10_token', 'xiaoyezishowminiapp', '测评分享小程序消息配置token' ),
	('wechat_app_push_config', '微信消息配置', '8_10_encoding_aes_key', 'jn9wIkD3GHvNAuPzqJTjz833aHfVebBhG1BpbbuAre9', '测评分享小程序消息配置EncodingAESKey' );


-- 清除缓存 DB=11

del dict_list_wechat_app_id
del dict_list_wechat_app_secret
