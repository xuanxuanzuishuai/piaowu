UPDATE `dict` SET `key_value` = '{"1":3095, "2":3096, "3":3097, "4":3096}' WHERE `key_code` = 'channel_dict' AND `type` = 'AGENT_CONFIG';

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
 ('AGENT_WEB_STUDENT_CONFIG', '代理支付回调', 'success_url_v1', 'https://referral.xiaoyezi.com/operation/pay?', '代理非微信调支付宝回调'),
  ('AGENT_WEB_STUDENT_CONFIG', '代理支付回调', 'cancel_url_v1', 'https://referral.xiaoyezi.com/operation/pay?', '代理非微信调支付宝回调'),
  ('AGENT_WEB_STUDENT_CONFIG', '代理支付回调', 'result_url_v1', 'https://referral.xiaoyezi.com/operation/pay?', '代理非微信调支付宝回调');