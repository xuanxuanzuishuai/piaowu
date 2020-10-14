-- 员工表添加字段
alter table employee
  add column leads_max_nums int unsigned not null default 0
comment "课管可分配的学生最大数量" after `email`;

-- 新增课管分配公池
INSERT INTO `leads_pool` (`name`, `target_type`, `target_set_id`, `status`, `create_time`, `operator`, `type`)
VALUES ('学生分配课管公共总池', 1, 0, 1, unix_timestamp, 10001, 1);
-- 设置dict常量数据
set @maxid = (select max(id)
              from leads_pool
              ORDER BY id desc;
);
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('leads_config', '线索分配相关配置', 'assistant_public_pool_id', '1', '助教分配学员总池子id');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('leads_config', '线索分配相关配置', 'course_manage_public_pool_id', @maxid, '课管分配学员总池子id');

-- 设置权限sql
set @pid = (select id
            from `privilege`
            where name = '课管服务');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('课管学员分配', '/org_web/leads_pool/cm_pool_list', unix_timstamp(), 'get', 1, '课管学员分配', @pid, 'cm_pool_list', 1);

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('新增课管线索池', '/org_web/leads_pool/add_cm_pool', unix_timstamp(), 'post', 0, '', @pid, 'add_cm_pool', 1);

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('编辑课管线索池', '/org_web/leads_pool/update_cm_pool', unix_timstamp(), 'post', 0, '', @pid, 'update_cm_pool', 1);

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('课管线索池详情', '/org_web/leads_pool/cm_pool_detail', unix_timstamp(), 'get', 0, '', @pid, 'cm_pool_detail', 1);

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('获取部门内的课管成员', '/org_web/employee/get_dept_course_manage', unix_timstamp(), 'get', 0, '', 0, 'get_dept_course_manage',1);



