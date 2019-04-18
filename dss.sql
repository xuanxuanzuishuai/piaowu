-- MySQL dump 10.13  Distrib 5.7.20, for osx10.13 (x86_64)
--
-- Host: panda-dev.crnyfz9gmtus.rds.cn-north-1.amazonaws.com.cn    Database: dss_dev
-- ------------------------------------------------------
-- Server version	5.6.40-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `dss_dev`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `dss_dev` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;

USE `dss_dev`;

--
-- Table structure for table `area`
--

DROP TABLE IF EXISTS `area`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `area` (
  `code` varchar(6) NOT NULL COMMENT '地区码',
  `level` int(11) NOT NULL COMMENT '区域级别，0: 国家级, 1: 省级, 2: 市级, 3: 区县级',
  `parent_code` varchar(6) DEFAULT NULL,
  `name` varchar(50) NOT NULL COMMENT '区域名称',
  `fullname` varchar(100) DEFAULT NULL COMMENT '包含省市县区的全名（省市区县连到一起）',
  `city` varchar(50) DEFAULT NULL COMMENT '城市（便于显示之用）',
  `province` varchar(50) DEFAULT NULL COMMENT '省份（便于显示之用）',
  `county` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bill`
--

DROP TABLE IF EXISTS `bill`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bill` (
  `id` varchar(20) NOT NULL COMMENT '自由订单id',
  `type` int(3) NOT NULL COMMENT '1: 客户充值  2: 客户提现  3: 客户购买课程,  4: 教师课时费收入   5: 教师提现   6: 分销商提现,  7: 分销商收入 8 老师购买课程 9 手工赠送课程 10 转介绍赠送课程 11 注册赠送课程 31 客户升级订单\n12:退单',
  `user_id` int(11) unsigned NOT NULL COMMENT '订单拥有者id',
  `user_type` int(1) NOT NULL DEFAULT '1' COMMENT '订单拥有者类型  1 学员 2 老师',
  `pay_status` int(1) DEFAULT '0' COMMENT '支付状态 0待支付 1 支付成功 -1 支付失败',
  `amount` int(11) DEFAULT '0' COMMENT '实付金额(分)',
  `msg` varchar(1024) DEFAULT NULL COMMENT '描述',
  `trade_no` varchar(128) DEFAULT NULL COMMENT '第三方支付流水号。如是退单代表student_course id”,”分割',
  `pay_channel` int(3) DEFAULT NULL COMMENT '支付渠道 1: 支付宝, 2: 微信, 3: 账户内支付, 4: PayPal, 5:天猫支付, 6:银行汇款, 7:扫码支付, 8 松鼠支付, 9 百度钱包, 10 京东支付, 0:其他\n21:微信公众号 22:微信h5',
  `create_time` int(11) NOT NULL COMMENT '订单创建时间戳',
  `end_time` int(11) DEFAULT NULL COMMENT '处理完成时间',
  `fee_type` enum('usd','cny') DEFAULT 'cny' COMMENT '账户类型  usd: 美元, cny: 人民币',
  `object_id` int(11) DEFAULT NULL COMMENT '对应物品id 如商品包id，商品id，课程单元id ',
  `object_type` int(1) DEFAULT NULL COMMENT '物品类型 1 商品包id 2 商品id  3 课程id ',
  `num` int(11) NOT NULL DEFAULT '1' COMMENT '订单包含物品数量',
  `app_id` int(11) NOT NULL COMMENT '业务线id 1 熊猫 2 松鼠 3 钢琴教室',
  `parent_id` varchar(20) NOT NULL DEFAULT '' COMMENT '父类订单id 针对补单、升级单 ',
  `operator_id` int(11) DEFAULT NULL COMMENT '后端补录操作者id',
  `operator_name` varchar(32) DEFAULT NULL COMMENT '后端补录操作者姓名',
  `chargeback_id` varchar(30) DEFAULT NULL COMMENT '退单id ',
  `source` int(2) NOT NULL DEFAULT '1' COMMENT '订单来源 1 自主下单 2 人工录入 ',
  `oprice` int(8) DEFAULT NULL COMMENT '应付价格(分)',
  `status` int(2) DEFAULT NULL COMMENT '订单状态  0 待确认 1已确认 9已退款',
  `remark` varchar(1500) DEFAULT NULL COMMENT 'ping++的charge',
  `pay_type` int(1) NOT NULL COMMENT '支付方式，1ping++，2微信，3支付宝',
  `callback_status` int(1) NOT NULL DEFAULT '0' COMMENT '请求发货接口状态：0默认，-1失败，1成功',
  `callback_res` varchar(255) DEFAULT NULL COMMENT '请求发货接口结果(一般是错误原因)',
  `class_room_order_id` varchar(40) NOT NULL DEFAULT '',
  `supplement_type` int(11) NOT NULL DEFAULT '0',
  `balance_amount` int(11) NOT NULL DEFAULT '0',
  `relation_bill_id` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `create_time` (`create_time`),
  KEY `end_time` (`end_time`),
  KEY `parent_id` (`parent_id`),
  KEY `object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campus`
--

DROP TABLE IF EXISTS `campus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campus` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `name` varchar(32) NOT NULL COMMENT '校区名称',
  `address` varchar(128) DEFAULT NULL COMMENT '地址',
  `create_time` int(10) NOT NULL COMMENT '创建时间戳',
  `desc` text COMMENT '校区介绍',
  `pic_url` varchar(64) DEFAULT NULL COMMENT '校区主图片',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='校区信息表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `classroom`
--

DROP TABLE IF EXISTS `classroom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classroom` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `name` varchar(32) NOT NULL COMMENT '教室名称',
  `campus_id` int(11) NOT NULL COMMENT '所属校区id',
  `desc` varchar(128) DEFAULT NULL COMMENT '教室介绍',
  `pic_url` varchar(128) DEFAULT NULL COMMENT '教室主图片',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='教室信息表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course`
--

DROP TABLE IF EXISTS `course`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `name` varchar(64) NOT NULL COMMENT '课程名称',
  `desc` varchar(256) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT '课程描述',
  `thumb` varchar(265) DEFAULT NULL COMMENT '课程图标',
  `app_id` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '课程所属业务线 1 熊猫 2 松鼠',
  `duration` int(6) unsigned NOT NULL COMMENT '课程时长(秒)',
  `type` int(2) NOT NULL DEFAULT '1' COMMENT '课程类型 1 体验课 2 正式课 4 智能硬件（灯条） 31 设备测试课 32 老师磨课 33 种子用户考核课 34 老师培训 ',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间戳',
  `update_time` int(10) unsigned DEFAULT NULL COMMENT '更新时间戳',
  `operator_id` int(11) DEFAULT NULL COMMENT '操作者id',
  `status` int(1) NOT NULL DEFAULT '0' COMMENT '课程状态  -1 未发布 0 不可用 1 正常',
  `level` int(2) NOT NULL DEFAULT '1' COMMENT '乐器的级别 1 一级 2 二级 3 三级 4 四级 5 五级',
  `oprice` int(8) unsigned DEFAULT NULL COMMENT '课程单价（分）',
  `class_lowest` smallint(3) unsigned DEFAULT NULL COMMENT '班型最低人数',
  `class_highest` int(3) unsigned DEFAULT NULL COMMENT '班型最高人数',
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_unique` (`name`,`app_id`,`duration`,`level`,`class_lowest`,`class_highest`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='课程表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dept`
--

DROP TABLE IF EXISTS `dept`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dept` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '部门id',
  `dept_name` varchar(32) NOT NULL DEFAULT '' COMMENT '部门名字',
  `relation` varchar(32) NOT NULL DEFAULT '' COMMENT '部门树',
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '父id',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间戳',
  `status` int(1) DEFAULT '1' COMMENT '部门状态 1 正常 0 不可用',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='部门表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dept_data`
--

DROP TABLE IF EXISTS `dept_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dept_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `dept_id` int(11) NOT NULL DEFAULT '0' COMMENT '部门id',
  `data_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '数据类型 ',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间戳',
  `dept_ids` varchar(255) NOT NULL DEFAULT '' COMMENT '所有权限部门',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='部门数据权限表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dict`
--

DROP TABLE IF EXISTS `dict`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dict` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `type` varchar(32) DEFAULT NULL COMMENT '字典类型 ',
  `key_name` varchar(45) DEFAULT NULL COMMENT '名称',
  `key_code` varchar(64) DEFAULT NULL COMMENT '代码',
  `key_value` varchar(255) DEFAULT NULL COMMENT '显示值',
  `desc` varchar(255) DEFAULT NULL COMMENT '描述',
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_2` (`type`,`key_code`) USING BTREE,
  KEY `type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=653 DEFAULT CHARSET=utf8 COMMENT='字典表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employee`
--

DROP TABLE IF EXISTS `employee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户id',
  `uuid` varchar(32) NOT NULL COMMENT '用户唯一id',
  `name` varchar(16) NOT NULL COMMENT '用户姓名',
  `role_id` int(10) NOT NULL COMMENT '角色ID 1 课程顾问  2 课管  3 代理商  4 教务  5 管理员  6 运营数据  7 王卉  8 教师招聘  9 财务  10 师资管理  11 测试  12 开发  13 运营实习-老师  14 市场推广  15 市场内容  16 技术支持  17 客服  18 超级管理员',
  `mobile` varchar(16) DEFAULT NULL COMMENT '手机号',
  `login_name` varchar(16) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT '登录名',
  `pwd` varchar(32) NOT NULL COMMENT '登录密码 md5',
  `status` int(1) DEFAULT '1' COMMENT '1正常 0废除',
  `created_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `dept_id` int(11) NOT NULL DEFAULT '0' COMMENT '部门id',
  `is_leader` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否leader',
  `last_login_time` int(10) DEFAULT NULL COMMENT '最后登录时间戳',
  `last_update_pwd_time` int(10) DEFAULT '0' COMMENT '最后更新密码时间戳',
  `teacher_id` int(11) DEFAULT NULL COMMENT '设备测试课，员工老师id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login_name` (`login_name`)
) ENGINE=InnoDB AUTO_INCREMENT=10924 DEFAULT CHARSET=utf8 COMMENT='员工表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employee_privilege`
--

DROP TABLE IF EXISTS `employee_privilege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_privilege` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户权限自增id',
  `employee_id` int(11) NOT NULL,
  `type` int(2) NOT NULL DEFAULT '1' COMMENT '权限类型 1 扩展权限 2 排除权限',
  `privilege_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COMMENT='员工权限表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group`
--

DROP TABLE IF EXISTS `group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `name` varchar(45) NOT NULL,
  `created_time` int(11) NOT NULL,
  `desc` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 COMMENT='角色组表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_privilege`
--

DROP TABLE IF EXISTS `group_privilege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_privilege` (
  `group_id` int(11) NOT NULL,
  `privilege_id` int(11) NOT NULL,
  UNIQUE KEY `primary_id` (`group_id`,`privilege_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='角色组权限表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `privilege`
--

DROP TABLE IF EXISTS `privilege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `privilege` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '权限自增id',
  `name` varchar(32) NOT NULL COMMENT '权限名称',
  `uri` varchar(256) DEFAULT NULL COMMENT '权限uri',
  `created_time` int(11) DEFAULT NULL COMMENT '创建时间',
  `method` varchar(45) DEFAULT NULL,
  `is_menu` int(1) NOT NULL DEFAULT '0' COMMENT '是否是菜单入口',
  `menu_name` varchar(45) DEFAULT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '父类权限id 0 顶级',
  `unique_en_name` varchar(64) NOT NULL DEFAULT '' COMMENT 'api唯一标识',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=319 DEFAULT CHARSET=utf8 COMMENT='权限表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role`
--

DROP TABLE IF EXISTS `role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '角色id',
  `name` varchar(32) NOT NULL COMMENT '角色名',
  `desc` varchar(128) DEFAULT NULL COMMENT '描述',
  `created_time` int(11) NOT NULL,
  `group_ids` varchar(32) NOT NULL COMMENT 'group_id以逗号隔开',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 COMMENT='角色表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schedule`
--

DROP TABLE IF EXISTS `schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `classroom_id` varchar(32) NOT NULL COMMENT '教室id',
  `course_id` int(11) NOT NULL COMMENT '课程id',
  `start_time` int(10) NOT NULL COMMENT '课程开始时间戳',
  `end_time` int(10) NOT NULL COMMENT '课程结束时间戳',
  `duration` int(8) NOT NULL COMMENT '上课持续时间，秒',
  `type` int(4) unsigned NOT NULL DEFAULT '1' COMMENT '课程类型 10 儿童体验课 11 儿童正式课  12 儿童1V1 20 成人体验课 21 成人正式课 ',
  `create_time` int(10) NOT NULL COMMENT '创建时间戳',
  `status` int(2) NOT NULL DEFAULT '0' COMMENT '课程状态 1: 预约成功, 2: 正在上课,  -1: 上课结束,  -2: 课程取消',
  PRIMARY KEY (`id`),
  KEY `start_time` (`start_time`),
  KEY `end_time` (`end_time`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='开课课程表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schedule_task`
--

DROP TABLE IF EXISTS `schedule_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule_task` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `course_id` int(11) NOT NULL COMMENT '课程类型',
  `start_time` int(10) NOT NULL COMMENT '开始时间戳',
  `end_time` int(10) NOT NULL COMMENT '结束时间戳',
  `classroom_id` int(10) DEFAULT NULL COMMENT '教室id',
  `create_time` int(10) NOT NULL COMMENT '创建时间戳',
  `status` int(1) NOT NULL COMMENT '状态 0 取消 1 正常预约 2 满员开课',
  PRIMARY KEY (`id`),
  KEY `start_time` (`start_time`),
  KEY `end_time` (`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='课程计划表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schedule_task_user`
--

DROP TABLE IF EXISTS `schedule_task_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule_task_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `st_id` int(11) NOT NULL COMMENT '课程计划id',
  `user_id` int(11) NOT NULL COMMENT '用户id 学员或是老师id',
  `user_role` int(2) NOT NULL COMMENT '用户角色 1 学员 2 老师',
  `create_time` int(10) NOT NULL COMMENT '创建时间戳',
  `user_status` int(1) NOT NULL COMMENT '用户状态 0 取消 1 报名 2 候补 ',
  `update_time` int(10) DEFAULT NULL COMMENT '更新时间戳',
  PRIMARY KEY (`id`),
  KEY `st_id` (`st_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='课程计划表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schedule_user`
--

DROP TABLE IF EXISTS `schedule_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL COMMENT '课程id',
  `user_id` int(11) NOT NULL COMMENT '用户id 对应学员id 老师id',
  `user_name` varchar(32) DEFAULT NULL COMMENT '用户姓名',
  `user_role` int(2) NOT NULL DEFAULT '0' COMMENT '用户角色 1 学生 2 老师',
  `user_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '学生子状态\n1 已预约 2 已取消 3 已请假 4 已出席 5 未出席\n老师子状态\n1 已分配 2 已请假 3 已出席 4 未出席',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `status` int(1) NOT NULL DEFAULT '1' COMMENT '课程用户状态 1 正常 0 不可用',
  PRIMARY KEY (`id`),
  KEY `sch_id` (`schedule_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='开课课程用户信息表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student`
--

DROP TABLE IF EXISTS `student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(32) NOT NULL COMMENT '用户唯一id',
  `name` varchar(32) NOT NULL COMMENT '用户姓名',
  `birthday` int(8) DEFAULT NULL COMMENT '出生日期 20100101',
  `gender` int(1) DEFAULT NULL COMMENT '性别 1 男 2 女 3 保密',
  `create_time` int(10) NOT NULL COMMENT '创建时间戳',
  `update_time` int(10) DEFAULT NULL COMMENT '更新时间戳',
  `channel_id` int(11) DEFAULT NULL COMMENT '渠道id',
  `channel_level` enum('S','A','B','C','D') DEFAULT NULL COMMENT '渠道的级别,一级渠道为虚拟渠道无级别, S级 A级 B级C级 D级',
  `mobile` varchar(16) NOT NULL,
  `thumb` varchar(256) DEFAULT NULL COMMENT '头像路径',
  `country_code` varchar(8) DEFAULT '86' COMMENT '国家代码',
  `has_used` tinyint(4) DEFAULT '-1' COMMENT '是否给宝贝使用过线上教育平台，0:否，1:是，-1:初始值',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='学员表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_course`
--

DROP TABLE IF EXISTS `student_course`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_course` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `student_id` int(11) NOT NULL COMMENT '学员id',
  `course_id` int(11) NOT NULL COMMENT '课程id',
  `lesson_count` int(6) NOT NULL DEFAULT '0' COMMENT '付费课程数量',
  `free_count` int(6) NOT NULL DEFAULT '0' COMMENT '免费课程数量',
  `freeze_count` int(6) NOT NULL DEFAULT '0' COMMENT '冻结课程数量',
  `used_lesson_count` int(6) DEFAULT '0' COMMENT '已用的正式课数量',
  `used_free_count` int(6) DEFAULT '0' COMMENT '已用赠送课次数',
  `deducted_count` int(6) DEFAULT '0' COMMENT '扣减数',
  `status` int(1) DEFAULT '1' COMMENT '用户课包状态 0 不可用 1 正常 2 过期',
  `create_time` int(10) DEFAULT NULL COMMENT '创建时间戳',
  `update_time` int(10) DEFAULT NULL COMMENT '更新时间戳',
  `bill_id` varchar(20) NOT NULL COMMENT '对应订单id',
  `expire_time` int(10) DEFAULT NULL COMMENT '过期时间戳',
  `unit_price` int(8) NOT NULL COMMENT '课程单价(分)',
  `chargeback_id` int(11) DEFAULT NULL COMMENT '退单id',
  `first_schedule_time` int(10) DEFAULT NULL COMMENT '首次上课时间',
  `upgrade_id` int(11) DEFAULT NULL COMMENT '升级课包id',
  `course_name` varchar(64) DEFAULT NULL COMMENT '课程名称',
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `expire_time` (`expire_time`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `student_id_2` (`student_id`,`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='学生课包表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_leave`
--

DROP TABLE IF EXISTS `student_leave`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_leave` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户对计划操作的限制。通常用来限制用户对某些操作的数量上的限制',
  `student_id` int(11) NOT NULL COMMENT '学生id',
  `key` varchar(200) NOT NULL COMMENT 'dict表中的system_env',
  `date` int(10) NOT NULL COMMENT '时间 年月，201808',
  `count` int(2) NOT NULL DEFAULT '0' COMMENT '次数',
  `create_time` int(10) DEFAULT NULL COMMENT '创建时间',
  `update_time` int(10) DEFAULT NULL COMMENT '更新时间',
  `version` int(11) NOT NULL DEFAULT '1' COMMENT '更新版本',
  `app_id` int(2) DEFAULT NULL COMMENT '业务线id',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teacher`
--

DROP TABLE IF EXISTS `teacher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` varchar(32) NOT NULL COMMENT '用户唯一id',
  `name` varchar(50) NOT NULL COMMENT '姓名',
  `mobile` varchar(20) NOT NULL COMMENT '手机号码',
  `gender` tinyint(1) DEFAULT NULL COMMENT '性别：3保密 1男 2女',
  `birthday` varchar(10) DEFAULT NULL COMMENT '出生日期',
  `thumb` varchar(200) DEFAULT '' COMMENT '头像地址',
  `country_code` varchar(10) DEFAULT '' COMMENT '国家代码',
  `province_code` varchar(10) DEFAULT '' COMMENT '省代码',
  `city_code` varchar(10) DEFAULT '' COMMENT '市代码',
  `district_code` varchar(10) DEFAULT '' COMMENT '区县代码',
  `address` varchar(255) DEFAULT '' COMMENT '详细地址',
  `channel_id` int(11) DEFAULT NULL COMMENT '渠道来源id',
  `id_card` varchar(20) DEFAULT '' COMMENT '身份证号',
  `bank_card_number` varchar(20) DEFAULT '' COMMENT '银行卡号',
  `opening_bank` varchar(50) DEFAULT '' COMMENT '开户行',
  `bank_reserved_mobile` varchar(20) DEFAULT NULL COMMENT '银行预留手机号',
  `type` tinyint(1) DEFAULT NULL COMMENT '老师类型，1兼职-固定，2兼职-非固定，3 OBT, 4 全职, 5 新兼职',
  `level` tinyint(2) DEFAULT NULL COMMENT '教授级别，1启蒙，2标准，3资深，4高级，5特级',
  `start_year` int(4) DEFAULT NULL COMMENT '教学起始年',
  `learn_start_year` int(4) DEFAULT NULL COMMENT '学琴起始年',
  `college_id` int(11) DEFAULT NULL COMMENT '毕业院校ID',
  `major_id` int(11) DEFAULT NULL COMMENT '所学专业ID',
  `graduation_date` int(6) DEFAULT NULL COMMENT '毕业年月',
  `education` tinyint(1) DEFAULT NULL COMMENT '老师学历，0未选择，1本科以下，2本科，3硕士，4博士',
  `music_level` tinyint(1) DEFAULT NULL COMMENT '演奏水平，0未选择，1车尔尼599，2车尔尼849，3车尔尼299，4车尔尼740',
  `teach_experience` text COMMENT '教学经历',
  `prize` text COMMENT '获奖情况',
  `teach_results` text COMMENT '教学成果',
  `teach_style` text COMMENT '教学风格',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '老师状态，1注册，2待入职，3在职，4冻结，5离职，6辞退，7不入职',
  `first_entry_time` int(10) DEFAULT NULL COMMENT '首次入职时间',
  `last_class_time` int(10) DEFAULT NULL COMMENT '最后上课时间',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  `is_export` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否曾导出，0否，1是',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile` (`mobile`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13097 DEFAULT CHARSET=utf8 COMMENT='老师基础信息表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teacher_college`
--

DROP TABLE IF EXISTS `teacher_college`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_college` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `college_name` varchar(100) DEFAULT '' COMMENT '大学名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否可用，0不可用，1可用',
  `is_quality` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是优质院校，0否，1是',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='老师毕业院校表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teacher_major`
--

DROP TABLE IF EXISTS `teacher_major`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_major` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `major_name` varchar(100) DEFAULT '' COMMENT '专业名称',
  `status` tinyint(1) DEFAULT '1' COMMENT '是否可用，0否，1是',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='老师毕业专业表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teacher_tag_relation`
--

DROP TABLE IF EXISTS `teacher_tag_relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_tag_relation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL COMMENT '老师ID',
  `tag_id` int(11) DEFAULT NULL COMMENT '标签ID',
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='老师标签对应关系表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teacher_tags`
--

DROP TABLE IF EXISTS `teacher_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '标签名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态，0停用，1正常',
  `type` tinyint(4) NOT NULL COMMENT '1主观 2客观',
  `operator_id` int(11) NOT NULL COMMENT '最后操作人ID',
  `parent_id` int(11) DEFAULT NULL COMMENT '标签父级ID',
  `create_time` int(10) NOT NULL,
  `update_time` int(10) NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_qr_ticket`
--

DROP TABLE IF EXISTS `user_qr_ticket`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_qr_ticket` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户id 对应学生id 老师id 机构id',
  `qr_ticket` varchar(128) DEFAULT NULL COMMENT '申请二维码所需ticket',
  `qr_url` varchar(128) DEFAULT NULL COMMENT '二维码图片路径',
  `type` int(1) NOT NULL DEFAULT '1' COMMENT '用户类型 1 学生 2 老师 3 机构',
  `landing_type` int(1) NOT NULL DEFAULT '1' COMMENT '扫描二维码后的跳转类型 1 为普通landing页 2 为小程序',
  `source` int(10) DEFAULT NULL COMMENT '学生转介绍需要细分渠道，（微信分享，自定义海报）需要对不同的渠道生成不同的二维码',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '生成时间戳',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户转介绍二维码信息';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_referee`
--

DROP TABLE IF EXISTS `user_referee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_referee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referee_id` int(11) DEFAULT NULL COMMENT '推荐人id 对应学生id，老师id，机构id',
  `referee_type` int(1) DEFAULT NULL COMMENT '推荐人类型 1 学生 2 老师 3 机构 4 琴加课学员代理 ',
  `user_id` int(11) DEFAULT NULL COMMENT '被推荐学生的user_id',
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referee_id` (`referee_id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户推荐关系表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_weixin`
--

DROP TABLE IF EXISTS `user_weixin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_weixin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户id 对应学生id、老师id',
  `user_type` int(1) NOT NULL COMMENT '用户类型 1 学生 2 老师',
  `open_id` varchar(32) NOT NULL COMMENT '微信open_id',
  `union_id` varchar(32) DEFAULT NULL COMMENT '微信union_id',
  `status` int(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `busi_type` int(3) NOT NULL COMMENT '业务类型 1：学生服务号 2：老师服务号 3：学生订阅号 4: 老师订阅号 5: XX小程序''',
  `app_id` int(11) DEFAULT NULL COMMENT '应用id',
  `thumb` varchar(256) DEFAULT NULL COMMENT '微信头像',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户微信表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2019-04-11 20:12:18
