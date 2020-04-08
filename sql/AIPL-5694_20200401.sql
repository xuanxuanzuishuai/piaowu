CREATE TABLE `ai_bill` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` varchar(20) NOT NULL COMMENT '订单id',
  `uuid` varchar(32) NOT NULL COMMENT '学生uuid',
  `auto_apply` tinyint(4) NOT NULL COMMENT '激活码激活 1 立即激活 0 手动激活',
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`)
) ENGINE=InnoDB COMMENT='订单激活码记录';


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('WEIXIN_STUDENT_CONFIG', '智能陪练微信端设置', 'success_url', 'https://dss-weixin.xiongmaopeilian.com/buy/succeed', '支付宝web，支付成功跳转'),
  ('WEIXIN_STUDENT_CONFIG', '智能陪练微信端设置', 'result_url', 'https://dss-weixin.xiongmaopeilian.com/buy/succeed', '微信H5，支付成功跳转');