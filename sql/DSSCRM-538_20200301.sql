CREATE TABLE `collection` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '集合类型1普通集合2公共集合',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '班级名称',
  `assistant_id` int(11) unsigned NOT NULL COMMENT '助教uid',
  `wechat_qr` varchar(255) NOT NULL COMMENT '二维码图片',
  `capacity` smallint(4) unsigned NOT NULL COMMENT '班级人数容量',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '开放状态1未开放2已开放',
  `remark` varchar(255) NOT NULL COMMENT '备注信息',
  `prepare_start_time` int(11) unsigned NOT NULL COMMENT '组班开始时间',
  `prepare_end_time` int(11) unsigned NOT NULL COMMENT '组班结束时间',
  `teaching_start_time` int(11) unsigned NOT NULL COMMENT '开班开始时间',
  `teaching_end_time` int(11) unsigned NOT NULL COMMENT '开班结束时间',
  `wechat_number` varchar(50) NOT NULL COMMENT '助教微信号',
  `create_uid` int(11) unsigned NOT NULL COMMENT '创建人uid',
  `create_time` int(11) unsigned NOT NULL COMMENT '创建时间',
  `update_uid` int(11) unsigned NOT NULL COMMENT '修改人uid',
  `update_time` int(11) unsigned NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) COMMENT='集合信息表';

CREATE TABLE `collection_course` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(11) unsigned NOT NULL COMMENT '课程ID',
  `collection_id` int(11) unsigned NOT NULL COMMENT 'collection表中的唯一ID',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '2' COMMENT '状态1无效2有效',
  `create_uid` int(11) unsigned NOT NULL COMMENT '创建人uid',
  `create_time` int(11) unsigned NOT NULL COMMENT '创建时间',
  `update_uid` int(11) unsigned NOT NULL COMMENT '修改人uid',
  `update_time` int(11) unsigned NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) COMMENT='集合和课程关联信息表';

INSERT INTO `collection`(`type`, `name`, `assistant_id`, `wechat_qr`, `capacity`, `status`, `remark`, `prepare_start_time`, `prepare_end_time`, `teaching_start_time`, `teaching_end_time`, `wechat_number`, `create_uid`, `create_time`, `update_uid`, `update_time`) VALUES (2, '公海班级', 0, 'prod/img//wxCode_cover/a0233a794ac92532a9e94c3ac12976cf.png', 0, 2, '如果没有可加入的班级，则加入“公海班”，推送默认二维码，不分配助教', 0, 0, 0, 0, 'xmlaoshi002', 0, 1583131273, 0, 0);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('collection_package_id', '学生集合购买商品包id', '1', '10324', '49元课包' );
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('role_id', '角色ID', 'ASSISTANT_ROLE_ID', '25', '助教');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('collection_process_status', '学生集合过程状态', '5', '已结班', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('collection_process_status', '学生集合过程状态', '4', '开班中', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('collection_process_status', '学生集合过程状态', '3', '待开班', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('collection_process_status', '学生集合过程状态', '2', '组班中', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('collection_process_status', '学生集合过程状态', '1', '待组班', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('collection_publish_status', '学生集合发布状态', '2', '已发布', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('collection_publish_status', '学生集合发布状态', '1', '未发布', NULL);

ALTER TABLE `student`
ADD COLUMN `allot_course_id` int(11) UNSIGNED NOT NULL COMMENT '进入班级时购买的课程id' AFTER `is_add_assistant_wx`;


INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('获取班级助教列表', '/org_web/collection/assistant_list', 1583206252, 'get', 0, '', 0, 'assistant_list_collection');
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('班级详情', '/org_web/collection/detail', 1583206252, 'get', 0, '', 0, 'detail_collection');
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('修改班级', '/org_web/collection/modify', 1583206252, 'post', 0, '', 0, 'modify_collection');
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('添加班级', '/org_web/collection/add', 1583206252, 'post', 0, '', 0, 'add_collection');
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('班级管理', '/org_web/collection/list', 1583206252, 'get', 1, '班级管理', 464, 'collection_list');
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('全部班级管理', '/org_web/collection/total_list', 1583206252, 'get', 1, '全部班级管理', 464, 'collection_total_list');