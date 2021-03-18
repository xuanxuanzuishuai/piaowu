
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('AGENT_WEB_STUDENT_CONFIG', '学生WEB端配置', 'success_url', 'https://referral-pre.xiaoyezi.com/operation/pay', 'web端下单回调地址'),
  ('AGENT_WEB_STUDENT_CONFIG', '学生WEB端配置', 'cancel_url', 'https://referral-pre.xiaoyezi.com/operation/pay', 'web端下单回调地址'),
  ('AGENT_WEB_STUDENT_CONFIG', '学生WEB端配置', 'result_url', 'https://referral-pre.xiaoyezi.com/operation/pay', 'web端下单回调地址'),
  ('AGENT_CONFIG', '代理系统设置', 'package_buy_page_url', 'https://referral-pre.xiaoyezi.com/operation/buy/product', '产品购买页面'),
  ('AGENT_CONFIG', '代理系统设置', 'share_card_logo', 'prod/referral/4dcca5069df44aff8afafc42a6e6a471.png', '分享卡片logo'),
  ('AGENT_CONFIG', '代理系统设置', 'channel_individual_teacher', '3096', '个人老师代理');

-- update channel_dict
-- $link = 'http://10.2.7.89:8000/operation/buy/product';