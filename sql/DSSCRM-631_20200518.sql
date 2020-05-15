-- 微信关注/取关相关记录
CREATE TABLE `wechat_openid_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(255) NOT NULL,
  `appid` tinyint(1) NOT NULL,
  `user_type` tinyint(1) NOT NULL,
  `busi_type` tinyint(1) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1为关注2为取消关注',
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='微信openid关注信息表';

-- 奖励相关微信推送切换模板
UPDATE `wechat_config` SET `content` = '{\"template_id\":\"G1hleUfvk7_lEi-Y5-2m5YBROuuySpwmQz9JcgmTHtM\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"\\u60a8\\u7684\\u9080\\u8bf7\\u597d\\u53cb\\u5956\\u52b1\\u5df2\\u53d1\\u653e\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u9080\\u8bf7\\u597d\\u53cb\\u9886\\u53d6\\u5956\\u52b1\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u9080\\u8bf7\\u597d\\u53cb\\u6ce8\\u518c\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u60a8\\u7684\\u597d\\u53cb{{mobile}}\\u5df2\\u6ce8\\u518c\\uff0c1\\u5929\\u6709\\u6548\\u671f\\u5df2\\u53d1\\u653e\\u3002\\u597d\\u53cb\\u4ed8\\u8d39\\u540e\\u60a8\\u53ef\\u4eab\\u73b0\\u91d1\\u7ea2\\u5305\\u5956\\u52b1\\uff01\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"\\u3010\\u70b9\\u6b64\\u6d88\\u606f\\u3011\\u67e5\\u770b\\u66f4\\u591a\\u9080\\u8bf7\\u8bb0\\u5f55\",\"color\":\"#FF8A00\"}}}' WHERE `id` = 1;

UPDATE `wechat_config` SET `content` = '{\"template_id\":\"G1hleUfvk7_lEi-Y5-2m5YBROuuySpwmQz9JcgmTHtM\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"\\u60a8\\u7684\\u9080\\u8bf7\\u597d\\u53cb\\u5956\\u52b1\\u5df2\\u53d1\\u653e\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u9080\\u8bf7\\u597d\\u53cb\\u9886\\u53d6\\u5956\\u52b1\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u9080\\u8bf7\\u597d\\u53cb\\u62a5\\u540d\\u667a\\u80fd\\u966a\\u7ec3\\u4f53\\u9a8c\\u8425\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u60a8\\u7684\\u597d\\u53cb{{mobile}}\\u5df2\\u62a5\\u540d\\u667a\\u80fd\\u966a\\u7ec3\\u4f53\\u9a8c\\u8425\\uff0c20\\u5143\\u73b0\\u91d1\\u7ea2\\u5305\\u5956\\u52b1\\u5df2\\u53d1\\u653e\\uff01\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"\\u3010\\u70b9\\u6b64\\u6d88\\u606f\\u3011\\u67e5\\u770b\\u66f4\\u591a\\u9080\\u8bf7\\u8bb0\\u5f55\",\"color\":\"#FF8A00\"}}}' WHERE `id` = 2;

UPDATE `wechat_config` SET `content` = '{\"template_id\":\"G1hleUfvk7_lEi-Y5-2m5YBROuuySpwmQz9JcgmTHtM\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"\\u60a8\\u7684\\u9080\\u8bf7\\u597d\\u53cb\\u5956\\u52b1\\u5df2\\u53d1\\u653e\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u9080\\u8bf7\\u597d\\u53cb\\u9886\\u53d6\\u5956\\u52b1\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u9080\\u8bf7\\u597d\\u53cb\\u8d2d\\u4e70\\u667a\\u80fd\\u966a\\u7ec3\\u5e74\\u5361\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u60a8\\u7684\\u597d\\u53cb{{mobile}}\\u5df2\\u8d2d\\u4e70\\u667a\\u80fd\\u966a\\u7ec3\\u5e74\\u5361\\u4e14\\u6ee17\\u5929\\uff0c100\\u5143\\u73b0\\u91d1\\u7ea2\\u5305\\u5956\\u52b1\\u5df2\\u53d1\\u653e\\uff01\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"\\u3010\\u70b9\\u6b64\\u6d88\\u606f\\u3011\\u67e5\\u770b\\u66f4\\u591a\\u9080\\u8bf7\\u8bb0\\u5f55\",\"color\":\"#FF8A00\"}}}' WHERE `id` = 3;