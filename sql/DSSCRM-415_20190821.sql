CREATE TABLE `referral` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id` INT NOT NULL COMMENT '介绍人id',
  `referee_id` INT NOT NULL COMMENT '被介绍人id',
  `type` TINYINT NOT NULL COMMENT '转介绍类型',
  `create_time` INT NOT NULL COMMENT '创建时间',
  `given_rewards` TINYINT NOT NULL COMMENT '是否发送了奖励 0 no 1 yes',
  `given_rewards_time` INT DEFAULT NULL COMMENT '奖励发送时间',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `referee_id_type_uniq` (`referee_id` ASC, `type` ASC),
  INDEX `referrer_id_idx` (`referrer_id` ASC));

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES
  ('generate_channel', '生成渠道', '6', '微信分享转介绍奖励');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES
  ('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'share_url', 'https://aipiano.xiaoyezi.com/ai_piano_app/#/share/register?ref=', '微信分享链接，需要拼接手机号');
