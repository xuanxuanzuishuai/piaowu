-- 创建智能定向导流真人
CREATE TABLE `ai_referral_to_panda_user`
(
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`   INT UNSIGNED NOT NULL COMMENT '学生id',
    `user_type`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '用户类型, 1:开班7天内有效练习天数小于4天 2:开班期内有效练习天数小于8天',
    `is_subscribe` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '是否关注公众号, 0:未关注 1:已关注',
    `is_send`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '是否已发送, 0:未发送 1:已发送',
    `send_status`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '发送状态, 0:默认值 1:发送失败 2:发送成功',
    `create_time`  INTEGER(11)  NOT NULL COMMENT '创建时间',
    `update_time`  INTEGER(11)  NOT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`)
) COMMENT ='智能定向导流真人用户表';

-- 添加IP白名单
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('IP_WHITE_LIST', 'IP白名单', 'crm', '*.*.*.*', NULL);
