INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES
('student_remark_status', '学生跟进状态', '10', '已加助教微信'),
('student_remark_status', '学生跟进状态', '20', '已拉入班级群'),
('student_remark_status', '学生跟进状态', '30', '课中跟进'),
('student_remark_status', '学生跟进状态', '40', '关单跟进'),
('student_remark_status', '学生跟进状态', '50', '首次付费后跟进'),
('student_remark_status', '学生跟进状态', '60', '续费跟进'),
('student_remark_status', '学生跟进状态', '70', '续费后跟进');

ALTER TABLE `student`
ADD COLUMN `latest_remark_status` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '最新跟进状态 10 已加助教微信 20 已拉入班级群 30 课中跟进 40 关单跟进 50 首次付费后跟进 60 续费跟进 70 续费后跟进' AFTER `wechat_account`,
ADD COLUMN `last_remark_id` INT(11) NULL DEFAULT '0' COMMENT '最后一次跟进记录ID' AFTER `latest_remark_status`;

CREATE TABLE `student_remark` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT '学生ID',
  `remark_status` tinyint(4) NOT NULL COMMENT '跟进状态 10 已加助教微信 20 已拉入班级群 30 课中跟进 40 关单跟进 50 首次付费后跟进 60 续费跟进 70 续费后跟进',
  `remark` text NOT NULL,
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  `employee_id` int(11) NOT NULL COMMENT '员工ID',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB COMMENT='学生跟进记录表';

CREATE TABLE `student_remark_image` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_remark_id` int(11) NOT NULL COMMENT '学生跟进ID',
  `image_url` varchar(80) NOT NULL COMMENT '图片地址',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1 正常 0 废除',
  `create_time` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `student_remark_id` (`student_remark_id`)
) ENGINE=InnoDB COMMENT='学生跟进记录图片';


INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`)
VALUES
('获取学生跟进记录', '/student/student_remark/remark_list', 1584932973, 'get', 0, '', 0, 'getStudentRemarkList'),
('添加学生跟进记录', '/student/student_remark/add', 1584932973, 'post', 0, '', 0, 'addStudentRemark');
