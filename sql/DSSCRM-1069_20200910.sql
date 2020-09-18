ALTER TABLE `student`
ADD COLUMN `is_add_course_wx` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '是否加课管微信' AFTER `is_join_ranking`;
ALTER TABLE `student`
ADD COLUMN `allot_course_time` INT(11) NOT NULL DEFAULT '0' COMMENT '分配课管时间' AFTER `is_add_course_wx`;

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('year_code_status', '年卡激活状态', '0', '未激活', '年卡激活');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('year_code_status', '年卡激活状态', '1', '已激活', '年卡激活');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('year_code_status', '年卡激活状态', '2', '已作废', '年卡激活');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('is_add_course_wx', '添加课管微信状态', '0', '未添加', '添加课管微信');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('is_add_course_wx', '添加课管微信状态', '1', '已添加', '添加课管微信');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_status', '朋友圈分享状态', '1', '待审核', '朋友圈分享');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_status', '朋友圈分享状态', '2', '已通过', '朋友圈分享');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_status', '朋友圈分享状态', '3', '未通过', '朋友圈分享');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_status', '朋友圈分享状态', '0', '未提交', '朋友圈分享');


insert into `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
value ('课管服务', '-', '1599824349', 'get', '1', '课管服务', '0', 'course_menu', '1');

select @id:=id from privilege where unique_en_name = 'course_menu';
insert into `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
value ('课管获取员工列表', '/org_web/student/search_list', '1599825155', 'get', '1', '课管学员列表', @id, 'course_student_list', '1');
insert into `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
value ('学员更新添加课管微信状态', '/org_web/student/update_add_course_status', '1600323018', 'post', '0', '', @id, 'add_course_status', '1');


 update student as a,
  (select * from (
  select student_id,create_time,ROW_NUMBER() OVER(PARTITION BY student_id ORDER BY create_time DESC) as num
  from student_course_manage_log ) as tmp
  where tmp.num=1
  ) as b set a.allot_course_time=b.create_time where a.id=b.student_id;