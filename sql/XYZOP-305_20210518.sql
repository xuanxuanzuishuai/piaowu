-- op
CREATE TABLE week_activity (
    `id` int(10) unsigned not null auto_increment comment '主键',
    `name` varchar(50) not null default '' comment '活动名称',
    `activity_id` int(10) unsigned not null default 0 comment 'operation_activity活动总表主键',
    `event_id` int(10) unsigned not null default 0 comment '事件id',
    `guide_word` varchar(1000) not null default '' comment '引导语',
    `share_word` varchar (1000) not null default '' comment '分享语',
    `start_time` int(10) not null default 0 comment '活动开始时间',
    `end_time` int(10) not null default 0 comment '活动结束时间',
    `enable_status` tinyint(1) not null default 1 comment '活动启用状态 1未启用，2启用, 3已禁用',
    `banner` varchar(255) not null default '' comment '顶部头图',
    `share_button_img` varchar(255) not null default '' comment '分享按钮图片',
    `award_detail_img` varchar(255) not null default '' comment '奖励说明图片',
    `upload_button_img` varchar(255) not null default '' comment '上传截图领奖按钮图片',
    `strategy_img` varchar(255) not null default '' comment '攻略图片',
    `create_time` int(10) not null default 0 comment '创建时间',
    `update_time` int(10) not null default 0 comment '最后更新时间',
    `operator_id` int(10) not null default 0 comment '最后操作人id',
    primary key `id`(`id`),
    KEY `idx_activity_id` (`activity_id`),
    KEY `idx_start_time` (`start_time`),
    KEY `idx_end_time` (`end_time`),
    KEY `idx_create_time` (`create_time`),
)engine=innodb charset=utf8mb4 comment '周周领奖活动表';

create table activity_poster (
    `id` int(10) unsigned not null auto_increment comment '主键',
    `activity_id` int(10) unsigned not null default 0 comment 'operation_activity活动总表主键',
    `poster_id` int(10) unsigned not null default 0 comment '海报库表主键',
    `status` tinyint(1) not null default 1 comment '上线状态 1线下，2上线',
    `is_del` tinyint(1) not null default 0 comment '是否已删除 0:为删除，1:删除'
    primary key `id`(`id`),
    key `idx_activity_id`(`activity_id`),
    key `idx_poster_id`(`poster_id`)
)engine=innodb charset=utf8 comment '活动和海报库关联表';

create table activity_ext(
    `id` int(10) unsigned not null auto_increment comment '主键',
    `activity_id` int(10) unsigned not null default 0 comment 'operation_activity活动总表主键',
    `award_rule` text CHARACTER SET utf8mb4 NOT NULL COMMENT '奖励规则',
    `remark` varchar(50) not null default '' comment '备注',
    primary key `id`(`id`),
    key `idx_activity_id`(`activity_id`)
)engine=innodb charset=utf8mb4 comment '领奖活动扩展信息表';

CREATE TABLE month_activity (
    `id` int(10) unsigned not null auto_increment comment '主键',
    `name` varchar(50) not null default '' comment '活动名称',
    `activity_id` int(10) unsigned not null default 0 comment 'operation_activity活动总表主键',
    `start_time` int(10) not null default 0 comment '活动开始时间',
    `end_time` int(10) not null default 0 comment '活动结束时间',
    `enable_status` tinyint(1) not null default 1 comment '活动启用状态 1未启用，2启用, 3已禁用',
    `banner` varchar(255) not null default '' comment '顶部头图',
    `make_poster_button_img` varchar(255) not null default '' comment '制作海报按钮图片',
    `make_poster_tip_word` varchar(500) not null default '' comment '制作海报提示语',
    `award_detail_img` varchar(255) not null default '' comment '奖励说明图片',
    `create_poster_button_img` varchar(255) not null default '' comment '生成海报页面制作按钮',
    `share_poster_tip_word` varchar(500) not null default '' comment '分享海报页面提示语',
    `create_time` int(10) not null default 0 comment '创建时间',
    `update_time` int(10) not null default 0 comment '最后更新时间',
    `operator_id` int(10) not null default 0 comment '最后操作人id',
    primary key `id`(`id`),
    KEY `idx_activity_id` (`activity_id`),
    KEY `idx_start_time` (`start_time`),
    KEY `idx_end_time` (`end_time`),
    KEY `idx_create_time` (`create_time`),
)engine=innodb charset=utf8mb4 comment '月月有奖活动表';

-- op 权限表
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES
    ('创建及修改周周有奖活动', '/op_web/week_activity/save', 1621407181, 'post', 0, '', 464, 'week_activity_save', 1),
    ('周周有奖活动列表', '/op_web/week_activity/list', 1621407181, 'get', 1, '', 464, 'week_activity_list', 1),
    ('周周有奖活动详情', '/op_web/week_activity/detail', 1621407181, 'get', 0, '', 464, 'week_activity_detail', 1),
    ('周周有奖活动启用状态修改', '/op_web/week_activity/enable_status', 1621407181, 'post', 0, '', 464, 'week_activity_enable_status', 1),
    ('周周有奖活动短息提醒', '/op_web/week_activity/send_msg', 1621407181, 'post', 0, '', 464, 'week_activity_send_msg', 1),
    ('周周有奖活动微信提醒', '/op_web/week_activity/push_weixin_msg', 1621407181, 'post', 0, '', 464, 'week_activity_push_weixin_msg', 1),
    ('创建及修改月月有奖活动', '/op_web/month_activity/save', 1621407181, 'post', 0, '', 464, 'month_activity_save', 1),
    ('月月有奖活动列表', '/op_web/month_activity/list', 1621407181, 'get', 1, '', 464, 'month_activity_list', 1),
    ('月月有奖活动详情', '/op_web/month_activity/detail', 1621407181, 'get', 0, '', 464, 'month_activity_detail', 1),
    ('月月有奖活动启用状态修改', '/op_web/month_activity/enable_status', 1621407181, 'post', 0, '', 464, 'month_activity_enable_status', 1),
    ('event事件列表', '/op_web/event/task_list', 1621407181, 'get', 0, '', 464, 'event_task_list', 1);


INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES
('ACTIVITY_ENABLE_STATUS', '活动启用状态', '1', '待启用', ''),
('ACTIVITY_ENABLE_STATUS', '活动启用状态', '2', '启用', ''),
('ACTIVITY_ENABLE_STATUS', '活动启用状态', '3', '已禁用', '');