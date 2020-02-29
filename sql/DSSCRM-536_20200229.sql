CREATE TABLE `dss_dev`.`banner` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL COMMENT '名称',
  `desc` VARCHAR(200) NOT NULL,
  `create_time` INT NOT NULL,
  `start_time` INT NOT NULL COMMENT '开始时间',
  `end_time` INT NOT NULL COMMENT '结束时间',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '是否生效',
  `operator` INT NOT NULL DEFAULT 0 COMMENT '操作人',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `show_main` TINYINT NOT NULL DEFAULT 0 COMMENT '是否展示主界面大图',
  `image_main` VARCHAR(200) NOT NULL COMMENT '主界面大图',
  `show_list` TINYINT NOT NULL COMMENT '是否展示列表图',
  `image_list` VARCHAR(45) NOT NULL COMMENT '列表图',
  `action_type` TINYINT NOT NULL COMMENT 'banner触发动作类型',
  `action_detail` VARCHAR(200) NOT NULL COMMENT 'banner触发动作参数',
  `filter` VARCHAR(45) NOT NULL COMMENT '过滤条件',
  PRIMARY KEY (`id`))
  COMMENT = '推广banner';
