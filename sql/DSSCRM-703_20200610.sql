# 个性化海报权限
set @parentMenuId = (select id from privilege where unique_en_name = 'operations_management');
INSERT INTO `privilege` (
	`name`,
	`uri`,
	`created_time`,
	`method`,
	`is_menu`,
	`menu_name`,
	`parent_id`,
	`unique_en_name`,
	`status`
)
VALUES
	(
		'个性化海报列表',
		'/org_web/poster_template/individualityList',
		1591674923,
		'get',
		1,
		'个性化海报列表',
		@parentMenuId,
		'individuality_list',
	1
	),
	(
		'标准海报列表',
		'/org_web/poster_template/standardList',
		1591675288,
		'get',
		1,
		'标准海报列表',
		@parentMenuId,
		'standard_list',
	1
	),
	(
		'文案列表',
		'/org_web/poster_template/textList',
		1591675337,
		'get',
		1,
		'文案列表',
		@parentMenuId,
		'text_list',
	1
	),
	(
		'个性化海报新增',
		'/org_web/poster_template/individualityAdd',
		1591761855,
		'post',
		0,
		'',
		'0',
		'individuality_add',
	1
	),
	(
		'标准海报新增',
		'/org_web/poster_template/standardAdd',
		1591781991,
		'post',
		0,
		'',
		'0',
		'standard_add',
	1
	),
	(
		'某条海报模板图信息',
		'/org_web/poster_template/getPosterInfo',
		1591846188,
		'get',
		0,
		'',
		'0',
		'one_template_poster_info',
	1
	),
	(
		'编辑海报模板图数据',
		'/org_web/poster_template/editPosterInfo',
		1591849043,
		'post',
		0,
		'',
		'0',
		'edit_template_poster_data',
	1
	),
	(
		'海报模板图文案添加',
		'/org_web/poster_template_word/addWord',
		1591856158,
		'post',
		0,
		'',
		'0',
		'template_poster_word_add',
	1
	),
	(
		'海报模板图列表',
		'/org_web/poster_template_word/wordList',
		1591857022,
		'get',
		0,
		'',
		'0',
		'template_poster_word_list',
	1
	),
	(
		'某条海报模板图的信息',
		'/org_web/poster_template_word/getWordInfo',
		1591857962,
		'get',
		0,
		'',
		'0',
		'one_template_poster_word_info',
	1
	),
	(
		'编辑海报模板文案',
		'/org_web/poster_template_word/editWordInfo',
		1591860081,
		'post',
		0,
		'',
		'0',
		'template_poster_word_edit',
	1
	);

# 海报模板图配置
INSERT INTO `dict` (
	`type`,
	`key_name`,
	`key_code`,
	`key_value`,
	`desc`
)
VALUES
	(
		'template_poster_config',
		'海报模板图配置',
		'1',
		'已下线',
NULL
	),
VALUES
	(
		'template_poster_config',
		'海报模板图配置',
		'2',
		'已上线',
NULL
	);

# 海报模板图信息
CREATE TABLE `template_poster` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poster_name` varchar(255) NOT NULL COMMENT '海报名称',
  `poster_url` varchar(255) NOT NULL COMMENT '海报路径',
  `poster_status` tinyint(1) NOT NULL COMMENT '1是下线2是上线',
  `order_num` tinyint(1) NOT NULL COMMENT '海报排序',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1个性化海报2标准海报',
  `operate_id` int(11) NOT NULL COMMENT '操作人id',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) COMMENT='海报模板图信息';