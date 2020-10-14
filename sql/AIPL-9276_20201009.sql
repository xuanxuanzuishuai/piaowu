CREATE TABLE `activity_sign_up` (
  `id`                int unsigned     NOT NULL AUTO_INCREMENT
  COMMENT '主键',
  `user_id`           int unsigned     NOT NULL DEFAULT '0'
  COMMENT '用户ID',
  `event_id`          int unsigned     NOT NULL DEFAULT '0'
  COMMENT '活动ID',
  `status`            tinyint unsigned NOT NULL DEFAULT '1'
  COMMENT '状态0无效1有效',
  `create_time`       int unsigned     NOT NULL DEFAULT '0'
  COMMENT '创建时间',
  `update_time`       int unsigned     NOT NULL DEFAULT '0'
  COMMENT '修改时间',
  `complete_time`     int unsigned     NOT NULL DEFAULT '0'
  COMMENT '目标完成时间',
  `complete_mileages` int unsigned     NOT NULL DEFAULT '0'
  COMMENT '完成的累积里程',
  PRIMARY KEY (`id`),
  KEY `idx_u_a` (`user_id`, `event_id`) USING BTREE
)
  COMMENT ='活动报名表';

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('halloween_config', '万圣节配置', 'halloween_event', '26', '万圣节相关的event');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('halloween_config', '万圣节配置', 'rank_limit', '500', '万圣节排行榜名次最大值');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('halloween_config', '万圣节配置', 'medal_task_type', '8', '万圣节徽章任务');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('halloween_config', '万圣节配置', 'process_task_type', '10', '万圣节游行任务');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('halloween_config', '万圣节配置', 'rank_task_type', '11', '万圣节排行任务');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('halloween_config', '万圣节配置', 'extra_task_type', '12', '万圣节额外任务');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('halloween_config', '万圣节配置', 'take_award_rank_limit', '100', '万圣节领取奖最低排名');
