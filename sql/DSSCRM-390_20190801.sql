INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'free_package', '{\"package_id\":0,\"package_name\":\"7\\u5929\\u65e0\\u9650\\u4f53\\u9a8c\\u5361\",\"price\":\"\\u514d\\u8d39\\u9886\\u53d6\"}', '免费领取体验时长产品包'),
  ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'pay_test_students', '', '支付测试用户uuid'),
  ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'success_url', 'http://aipiano-pre.xiaoyezi.com/ai_piano_app/#/paySuccess', '支付宝web，支付成功跳转'),
  ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'cancel_url', 'http://aipiano-pre.xiaoyezi.com/ai_piano_app/#/payFail', '支付宝web，支付失败跳转'),
  ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'result_url', 'http://aipiano-pre.xiaoyezi.com/ai_piano_app/#/paySuccess', '微信H5，支付成功跳转');