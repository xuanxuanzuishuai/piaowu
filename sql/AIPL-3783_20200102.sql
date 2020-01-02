CREATE TABLE `wechat_cs` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '自增加id',
  `name` VARCHAR(45) NOT NULL COMMENT '微信号',
  `qr_url` VARCHAR(256) NOT NULL COMMENT '微信二维码图片url',
  `status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '发布状态 0 未发布 1 已发布 ',
  `create_time` INT(10) NOT NULL,
  `update_time` INT(10) NOT NULL,
  PRIMARY KEY (`id`))
COMMENT = '微信客服信息表';