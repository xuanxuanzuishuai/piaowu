-- 外呼日志表
CREATE TABLE `callcenter_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
    `employee_id` int(11) NOT NULL DEFAULT '0' COMMENT '员工ID',
    `student_id` int(11) NOT NULL DEFAULT '0' COMMENT '学生ID',
    `call_type` int(4) NOT NULL DEFAULT '0' COMMENT '类型 1001 来电 1002 外呼',
    `seat_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '座席类型 1 天润 2 容联',
    `seat_id` varchar(8) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '座席号',
    `unique_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '一通呼叫的唯一标识',
    `customer_number` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '客户号码',
    `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间戳',
    `ring_time` int(11) NOT NULL DEFAULT '0' COMMENT '响铃时间戳',
    `connect_time` int(11) NOT NULL DEFAULT '0' COMMENT '接通时间戳',
    `finish_time` int(11) NOT NULL DEFAULT '0' COMMENT '挂机时间戳',
    `talk_time` int(11) NOT NULL DEFAULT '0' COMMENT '通话时间',
    `call_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '呼叫状态',
    `record_file` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '录音文件名',
    `show_code` varchar(16) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '外显号码',
    `site_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '后台类型 0 crm 1 erp',
    `cdr_enterprise_id` int(11) NOT NULL DEFAULT '0' COMMENT '企业id',
    `user_unique_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户自定义唯一id',
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`employee_id`),
    KEY `idx_student_id` (`student_id`),
    KEY `idx_unique_id` (`unique_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='呼叫中心日志表';

-- 员工坐席表
CREATE TABLE `employee_seat` (
   `id` int(11) NOT NULL AUTO_INCREMENT,
   `employee_id` int(11) NOT NULL COMMENT '员工ID',
   `seat_type` enum('1','2','3','4') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '座席类型（1：容联七陌 2：天润手动外呼 3：天润自动外呼 4：天润客服）',
   `seat_id` varchar(8) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
   `seat_tel` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
   `pwd` varchar(45) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
   `extend_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '容联专用字段 0 默认值 1 gateway 2 Local 3 sip',
   `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '0 停用  1 在用',
   `create_time` int(11) NOT NULL,
   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='员工呼叫中心坐席表';

-- DICT 增加数据
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  -- 容联外呼字段
  ('ronglian_extend_type', '容联外呼类型', '1', 'gateway', ''),
  ('ronglian_extend_type', '容联外呼类型', '2', 'Local', ''),
  ('ronglian_extend_type', '容联外呼类型', '3', 'sip', ''),
  -- 容联外呼方式数据
  ('ronglian_dial_type', '容联外呼类型选项', '1', '话机', ''),
  ('ronglian_dial_type', '容联外呼类型选项', '2', '手机', ''),
  -- 容联坐席数据
  ('seat_type', '座席类型', '1', '容联外呼', NULL),
  -- 容联接口报错数据
  ('rl_error', '容联接口返回错误值', '400', '请求有误，请检查传递的参数是否合法', ''),
  ('rl_error', '容联接口返回错误值', '401', '账户配置问题', ''),
  ('rl_error', '容联接口返回错误值', '403', '鉴权失败', ''),
  ('rl_error', '容联接口返回错误值', '404', '坐席未找到', ''),
  ('rl_error', '容联接口返回错误值', '407', '坐席无法接听电话（坐席没有登录', ''),
  ('rl_error', '容联接口返回错误值', '408', '坐席忙碌，无法接听', ''),
  ('rl_error', '容联接口返回错误值', '409', '调用者指定的接听方式，不可用', ''),
  ('rl_error', '容联接口返回错误值', '500', '服务器错误', '');


-- 添加外呼前缀
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('system_env', '外呼前缀', 'call_center_app_prefix', 'dss', '');

-- 新增权限
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('设置员工坐席', '/employee/employee/setSeat', 1600446535, 'post', 0, '', 52, 'set_seat', 1),
  ('解绑员工坐席', '/employee/employee/delSeat', 1600446535, 'post', 0, '', 52, 'del_seat', 1),
  ('学生外呼记录列表', '/student/student/call_list', 1600446535, 'get', 0, '', 344, 'call_list', 1),
  ('外呼拨号接口', '/call/call/dialOut', 1600446535, 'post', 0, '', 344, 'dail_out', 1);