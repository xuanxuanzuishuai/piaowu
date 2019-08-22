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