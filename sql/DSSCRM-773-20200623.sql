CREATE TABLE `wx_answer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `q_id` int(10) unsigned NOT NULL COMMENT '题目id',
  `answer` text NOT NULL COMMENT '答案',
  `sort` tinyint(1) unsigned NOT NULL COMMENT '排序 数大后发',
  `status` tinyint(2) unsigned NOT NULL COMMENT '状态 0 不可用 1 正常',
  PRIMARY KEY (`id`),
  KEY `q_id` (`q_id`)
) COMMENT='微信公众号自动回复答案表';

CREATE TABLE `wx_question` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `title` varchar(32) NOT NULL COMMENT '题目标题',
  `creator_id` int(11) unsigned NOT NULL COMMENT '创建人id',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间戳',
  `status` tinyint(2) unsigned NOT NULL COMMENT '状态 0 不可用 1 正常',
  PRIMARY KEY (`id`),
  KEY `title` (`title`)
) COMMENT='微信公众号自动回复题目表';

ALTER TABLE `wx_answer`
ADD COLUMN `type` TINYINT(1) UNSIGNED NOT NULL COMMENT '答案类型 1文字 2图片' AFTER `status`;

INSERT INTO `wx_question` (`id`, `title`, `creator_id`, `create_time`, `status`) VALUES ('1', '自动回复', '11018', '1594034107', '1');
INSERT INTO `wx_answer` (`id`, `q_id`, `answer`, `sort`, `status`, `type`) VALUES ('1', '1', '您好，微信平台客服在线服务时间为12:00-21:00，非在线时间咨询可拨打客服电话 400-029-260', '1', '1', '1');

