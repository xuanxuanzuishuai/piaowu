create table class_record (
  id         int                  auto_increment,
  org_id     int         not null,
  class_id   int         not null,
  students   text        not null
  comment '教室学生，json字符串',
  token      varchar(64) not null
  comment '本节课token',
  start_time int         not null
  comment '开始上课时间',
  end_time   int         not null default '0'
  comment '结束上课时间',
  primary key (id),
  index index_token (org_id, class_id, token)
)
  comment '集体课上课记录表';

CREATE TABLE `classroom_device` (
  `id`              int(10) unsigned NOT NULL AUTO_INCREMENT,
  `org_id`          int(10) unsigned NOT NULL
  COMMENT '机构id',
  `teacher_mac`     varchar(45)      NOT NULL
  COMMENT '老师端pc设备mac',
  `create_time`     int(11)          NOT NULL
  COMMENT '初始化时间',
  `status`          tinyint(2)       NOT NULL DEFAULT '1'
  COMMENT '状态 0不可用 1正常',
  `student_devices` text             NOT NULL
  COMMENT '学生设备列表，json数组，数组顺序代表实际座位顺序',
  PRIMARY KEY (`id`)
)
  COMMENT = '集体课教室设备列表';

insert into dict (type, key_name, key_code, key_value, `desc`)
values ('classroom_app_config', '最大离线登录次数', 'used_offline', 3, '最大离线登录次数');