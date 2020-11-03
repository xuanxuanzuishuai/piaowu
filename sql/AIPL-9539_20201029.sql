CREATE TABLE `student_login_info` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `student_id` int(11) NOT NULL COMMENT '学员ID',
  `token` varchar(100) NOT NULL DEFAULT '' COMMENT '登录时token',
  `device_model` varchar(50) NOT NULL DEFAULT '' COMMENT '设备名称',
  `os` varchar(50) NOT NULL DEFAULT '' COMMENT '设备操作系统',
  `idfa` varchar(50) NOT NULL DEFAULT '' COMMENT '苹果设备唯一编号',
  `imei` varchar(50) NOT NULL DEFAULT '' COMMENT '国际移动设备识别码',
  `android_id` varchar(50) NOT NULL DEFAULT '' COMMENT 'android设备的唯一识别码',
  `has_review_course` tinyint(4) NOT NULL COMMENT '是否有点评课 0无 1体验卡2周 2年卡',
  `sub_end_time` int(10) NOT NULL COMMENT '会员到期时间',
  `is_experience` tinyint(4) NOT NULL DEFAULT '2' COMMENT '是否在体验期 1：是 2：不是',
  `create_time` int(10) NOT NULL COMMENT '记录创建时间',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `idfa` (`idfa`),
  KEY `imei` (`imei`),
  KEY `android_id` (`android_id`)
) COMMENT='用户登录流水表';


CREATE TABLE `student_brush` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `student_id` int(11) NOT NULL COMMENT '学员ID',
  `brush_no` varchar(32) NOT NULL COMMENT '同用户刷单识别号',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `brush_no` (`brush_no`)
) COMMENT='刷单用户记录表';


CREATE TABLE `apple_devices` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `apple_code` varchar(20) NOT NULL DEFAULT '' COMMENT '设备代号',
  `apple_model` varchar(20) NOT NULL DEFAULT '' COMMENT '设备型号',
  PRIMARY KEY (`id`)
) COMMENT='苹果设备映射表';


INSERT INTO `apple_devices` (`apple_code`, `apple_model` )
VALUES
	('iPhone13,1', 'iPhone 12 mini' ),
	('iPhone13,2', 'iPhone 12' ),
	('iPhone13,3', 'iPhone 12 Pro' ),
	('iPhone13,4', 'iPhone 12 Pro Max' ),

	('iPhone12,8', 'iPhone SE 2' ),
	('iPhone12,1', 'iPhone 11' ),
	('iPhone12,3', 'iPhone 11 Pro' ),
	('iPhone12,5', 'iPhone 11 Pro Max' ),

	('iPhone11,8', 'iPhone XR' ),
	('iPhone11,2', 'iPhone XS' ),
	('iPhone11,4', 'iPhone XS Max' ),
	('iPhone11,6', 'iPhone XS Max' ),

	('iPhone10,1', 'iPhone 8' ),
	('iPhone10,4', 'iPhone 8' ),
	('iPhone10,2', 'iPhone 8 Plus' ),
	('iPhone10,5', 'iPhone 8 Plus' ),
	('iPhone10,3', 'iPhone X' ),
	('iPhone10,6', 'iPhone X' ),

	('iPhone9,1', 'iPhone 7' ),
	('iPhone9,3', 'iPhone 7' ),
	('iPhone9,2', 'iPhone 7 Plus' ),
	('iPhone9,4', 'iPhone 7 Plus' ),

	('iPhone8,1', 'iPhone 6s' ),
	('iPhone8,2', 'iPhone 6s Plus' ),
	('iPhone8,4', 'iPhone SE' ),

	('iPhone7,2', 'iPhone 6' ),
	('iPhone7,1', 'iPhone 6 Plus' ),

	('iPhone6,1', 'iPhone 5s' ),
	('iPhone6,2', 'iPhone 5s' ),

	('iPhone5,1', 'iPhone 5' ),
	('iPhone5,2', 'iPhone 5' ),
	('iPhone5,3', 'iPhone 5c' ),
	('iPhone5,4', 'iPhone 5c' ),

	('iPhone4,1', 'iPhone 4S' ),

	('iPhone3,1', 'iPhone 4' ),
	('iPhone3,2', 'iPhone 4' ),
	('iPhone3,3', 'iPhone 4' ),

	('iPhone1,1', 'iPhone 2G' ),
	('iPhone1,2', 'iPhone 3G' ),
	('iPhone2,1', 'iPhone 3GS' ),


	('iPad1,1', 'iPad' ),
	('iPad2,1', 'iPad 2' ),
	('iPad2,2', 'iPad 2' ),
	('iPad2,3', 'iPad 2' ),
	('iPad2,4', 'iPad 2' ),

	('iPad3,1', 'iPad 3' ),
	('iPad3,2', 'iPad 3' ),
	('iPad3,3', 'iPad 3' ),
	('iPad3,4', 'iPad 4' ),
	('iPad3,5', 'iPad 4' ),
	('iPad3,6', 'iPad 4' ),
	('iPad6,11', 'iPad 5' ),
	('iPad6,12', 'iPad 5' ),
	('iPad7,5', 'iPad 6' ),
	('iPad7,6', 'iPad 6' ),
	('iPad7,11', 'iPad 7' ),
	('iPad7,12', 'iPad 7' ),
	('iPad11,6', 'iPad 8' ),
	('iPad11,7', 'iPad 8' ),
	('iPad4,1', 'iPad Air' ),
	('iPad4,2', 'iPad Air' ),
	('iPad4,3', 'iPad Air' ),
	('iPad5,3', 'iPad Air 2' ),
	('iPad5,4', 'iPad Air 2' ),
	('iPad11,3', 'iPad Air 3' ),
	('iPad11,4', 'iPad Air 3' ),
	('iPad13,1', 'iPad Air 4' ),
	('iPad12,2', 'iPad Air 4' ),
	('iPad6,3', 'iPad Pro 9.7-inch' ),
	('iPad6,4', 'iPad Pro 9.7-inch' ),
	('iPad6,7', 'iPad Pro 12.9-inch' ),
	('iPad6,8', 'iPad Pro 12.9-inch' ),

	('iPad7,1', 'iPad Pro 12.9-inch 2' ),
	('iPad7,2', 'iPad Pro 12.9-inch 2' ),
	('iPad7,3', 'iPad Pro 10.5-inch' ),
	('iPad7,4', 'iPad Pro 10.5-inch' ),

	('iPad8,1', 'iPad Pro 11-inch' ),
	('iPad8,2', 'iPad Pro 11-inch' ),
	('iPad8,3', 'iPad Pro 11-inch' ),
	('iPad8,4', 'iPad Pro 11-inch' ),
	('iPad8,5', 'iPad Pro 12.9-inch 3' ),
	('iPad8,6', 'iPad Pro 12.9-inch 3' ),
	('iPad8,7', 'iPad Pro 12.9-inch 3' ),
	('iPad8,8', 'iPad Pro 12.9-inch 3' ),
	('iPad8,9', 'iPad Pro 11-inch 2' ),
	('iPad8,10', 'iPad Pro 11-inch 2' ),
	('iPad8,11', 'iPad Pro 12.9-inch 4' ),
	('iPad8,12', 'iPad Pro 12.9-inch 4' ),

	('iPad2,5', 'iPad mini' ),
	('iPad2,6', 'iPad mini' ),
	('iPad2,7', 'iPad mini' ),

	('iPad4,4', 'iPad mini 2' ),
	('iPad4,5', 'iPad mini 2' ),
	('iPad4,6', 'iPad mini 2' ),

	('iPad4,7', 'iPad mini 3' ),
	('iPad4,8', 'iPad mini 3' ),
	('iPad4,9', 'iPad mini 3' ),

	('iPad5,1', 'iPad mini 4' ),
	('iPad5,2', 'iPad mini 4' ),

	('iPad11,1', 'iPad mini 5' ),
	('iPad11,2', 'iPad mini 5' ),

	('iPod1,1', 'iTouch' ),
	('iPod2,1', 'iTouch2' ),
	('iPod3,1', 'iTouch3' ),
	('iPod4,1', 'iTouch4' ),
	('iPod5,1', 'iTouch5' ),
	('iPod7,1', 'iTouch6' ),
	('iPod9,1', 'iTouch7' ),

	('i386', 'iPhone Simulator' ),
	('x86_64', 'iPhone Simulator' );