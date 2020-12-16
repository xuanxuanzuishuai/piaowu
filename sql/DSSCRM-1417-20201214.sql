INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_mchid', '微信的mch_no', '8_1', '1573168341', '智能陪练商户号');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_api_cert_pem', '微信的api的cert的pem路径', '8_1', '/Users/xyz/Data/cert/apiclient_cert.pem', '微信支付的api的cert的pem路径');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('wechat_api_key_pem', '微信的api的key的pem路径', '8_1', '/Users/xyz/Data/cert/apiclient_key.pem', '微信支付的api的key的pem路径');

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('queue_config', '系统设置', 'NSQ_LOOKUPS', '172.17.209.127:4161', '消息队列Lookups');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('queue_config', '系统设置', 'NSQ_TOPIC_PREFIX', 'pre_', '消息队列topic前缀');

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('message_rule_config', '消息推送规则', 'receive_red_pack_rule_id', '2', '获取红包相关规则');