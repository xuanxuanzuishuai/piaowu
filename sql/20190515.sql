ALTER TABLE `schedule`
ADD COLUMN `c_t_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'class_task_id' AFTER `update_time`;


CREATE TABLE `class_task_price` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `c_t_id` int(11) NOT NULL COMMENT 'class_task_id',
  `student_id` int(11) NOT NULL COMMENT '学生id',
  `price` int(10) NOT NULL COMMENT '价格，单位分',
  `status` int(1) NOT NULL COMMENT '用户状态 0 取消 1 报名 2 候补 ',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='班课学生课单价表';


ALTER TABLE `class_user`
DROP COLUMN `price`;