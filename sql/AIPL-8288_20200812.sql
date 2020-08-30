INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'sign_in_medal', '12', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'play_piano_time_medal', '13', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'share_grade_medal', '14', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'both_hand_evaluate_medal', '15', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'play_distinct_lesson_medal', '16', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'finish_first_practice_medal', '17', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'receive_max_sign_award_medal', '18', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'evaluate_zero_medal', '19', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'finish_var_task_count_medal', '20', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'add_up_var_credit_medal', '21', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('medal_config', '奖章活动', 'change_thumb_and_name_medal', '22', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('ALI_OSS_CONFIG', '阿里OSS相关配置', 'green_text_check_type', 'antispam', 'green_text_check_type');

CREATE TABLE `report_data_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` tinyint(1) NOT NULL COMMENT '上报的类型(签到1练琴2双手评测3分享成绩4音基题5示范视频6浏览重难点7识谱提升8知名人士9)',
  `student_id` int(11) NOT NULL COMMENT '用户id',
  `report_data` varchar(255) NOT NULL COMMENT '上报的信息内容',
  `create_time` int(11) NOT NULL COMMENT '上报的时间',
  PRIMARY KEY (`id`)
) COMMENT='上报信息记录表';

CREATE TABLE `medal_reach_num` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT '用户id',
  `medal_type` tinyint(1) NOT NULL COMMENT '奖章类型(天天成长17勤奋小标兵18宣传小能手19文艺大队长24首次练琴21签到玩家22王者起航23任务达人26音符大户20知名人士27)',
  `valid_num` int(11) NOT NULL DEFAULT '0' COMMENT '每个奖章的不同等级要求的达成数不同，这是每个奖章用户的有效达成数',
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) COMMENT='奖章有效计数表,不同计数不同等级奖章';

CREATE TABLE `student_medal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT '用户id',
  `medal_id` int(11) NOT NULL COMMENT '奖章id对应erp_goods_v1的id',
  `medal_category_id` int(11) NOT NULL COMMENT '奖章类别id,冗余字段',
  `task_id` int(11) NOT NULL COMMENT '奖章对应活动的id',
  `report_log_id` int(11) NOT NULL COMMENT '在上报哪条数据的时候用户得到了奖章',
  `create_time` int(11) NOT NULL,
  `is_show` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '是否已经给用户展示过0没展示1已经展示',
  PRIMARY KEY (`id`)
) COMMENT='用户获取的奖章信息';

CREATE TABLE `student_medal_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT '学生id',
  `medal_category_id` int(11) NOT NULL COMMENT '获得奖章的类别id',
  `is_default` tinyint(1) NOT NULL COMMENT '是否是学生设定的默认展示奖章，1为是0为否',
  `create_time` int(11) NOT NULL COMMENT '首次获得此类奖章的时间',
  `update_time` int(11) NOT NULL COMMENT '最新获得此奖章的时间',
  PRIMARY KEY (`id`)
) COMMENT='用户获取的奖章类别信息';