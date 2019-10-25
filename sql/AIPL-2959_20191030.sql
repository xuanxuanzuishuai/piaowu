create table question
(
  id                 int auto_increment,
  exam_org           int     not null
  comment '机构，央音、中音等',
  level              int     not null
  comment '等级，一级二级等',
  catalog            int     null null
  comment '大类，乐理题型，练耳题型等',
  sub_catalog        int     not null
  comment '小类，符号识别，符号听辨等',
  template           int     not null
  comment '模板类型，点选类型，拖拽类型等',
  content_text       varchar(127) comment '题目内容，文字',
  content_img        varchar(255) comment '题目内容，图片',
  content_audio      varchar(255) comment '题目内容，音频',
  content_text_audio varchar(255) comment '题目内容，文字对应语音',
  audio_set          varchar(255) comment '音频设置，播放遍数等,json格式存储',
  opern              varchar(127) comment '关联曲谱',
  options            text    not null
  comment '答案选项，json格式',
  answer_explain     varchar(512) comment '答案解析,json格式',
  status             tinyint not null
  comment '状态，1上线 2下线 3废弃',
  employee_id        int     not null
  comment '最后操作人ID',
  update_time        int,
  create_time        int     not null,
  primary key (id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COMMENT = '题目表';

create table question_tag
(
  id          int auto_increment,
  tag         varchar(16) not null
  comment '标签',
  employee_id int         not null
  comment '最后操作人ID',
  status      tinyint     not null
  comment '状态，0废除 1正常',
  update_time int,
  create_time int         not null,
  primary key (id),
  unique (tag)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COMMENT = '题目标签表';

create table question_tag_relation
(
  question_id     int     not null,
  question_tag_id int     not null,
  status          tinyint not null
  comment '状态，0废除 1正常'
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COMMENT = '题目标签关系表';


create table question_catalog
(
  id          int                  auto_increment,
  type        tinyint     not null
  comment '1机构 2级别 3一级分类 4二级分类',
  catalog     varchar(20) not null
  comment '分类',
  parent_id   int         not null default '0'
  comment '父类ID',
  mini_show   tinyint     not null default '1'
  comment '是否在小程序端显示 1显示 0不显示',
  status      tinyint     not null default '1'
  comment '0废弃 1正常',
  update_time int,
  create_time int         not null,
  primary key (id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COMMENT = '题目分类表，包含机构，级别，大类和小类';

insert into question_catalog (catalog, type, parent_id, create_time, mini_show)
values ('中央院', 1, 0, 1571972262, 1), -- 1
       ('中国院', 1, 0, 1571972262, 1), -- 2

       ('初级', 2, 1, 1571972262, 1), -- 3
       ('一级', 2, 2, 1571972262, 1), -- 4
       ('二级', 2, 2, 1571972262, 1), -- 5

       ('乐理题型', 3, 3, 1571972262, 1), -- 6
       ('练耳题型', 3, 3, 1571972262, 1), -- 7
       ('音乐常识题型', 3, 3, 1571972262, 1), -- 8
       ('视唱模唱', 3, 3, 1571972262, 0), -- 9

       ('节奏识别', 4, 6, 1571972262, 1),
       ('音高识别', 4, 6, 1571972262, 1),
       ('符号识别', 4, 6, 1571972262, 1),
       ('模进填空', 4, 6, 1571972262, 1),
       ('音程识别', 4, 6, 1571972262, 1),
       ('拖拽小节', 4, 6, 1571972262, 0),

       ('节奏听辨', 4, 7, 1571972262, 1),
       ('音高听辨', 4, 7, 1571972262, 1),
       ('符号听辨', 4, 7, 1571972262, 1),
       ('旋律听辨', 4, 7, 1571972262, 1),
       ('音程听辨', 4, 7, 1571972262, 1),
       ('小鼓敲击', 4, 6, 1571972262, 0),

       ('看图识别乐器', 4, 8, 1571972262, 1),
       ('听辨乐器', 4, 8, 1571972262, 1),
       ('听辨歌唱声部及演唱形式', 4, 8, 1571972262, 1),
       ('听辨中外民歌', 4, 8, 1571972262, 1),

       ('视唱', 4, 9, 1571972262, 1),
       ('模唱', 4, 9, 1571972262, 1),

       -- 一级
       ('乐理', 3, 4, 1571972262, 1), -- 28
       ('听辨', 3, 4, 1571972262, 1), -- 29
       ('视唱', 3, 4, 1571972262, 0), -- 30

       -- 二级
       ('乐理', 3, 5, 1571972262, 1), -- 31
       ('听辨题型', 3, 5, 1571972262, 1), -- 32
       ('视唱', 3, 5, 1571972262, 0), -- 33

       -- 乐理
       ('单音', 4, 28, 1571972262, 1),
       ('音程', 4, 28, 1571972262, 1),
       ('节奏节拍', 4, 28, 1571972262, 1),
       ('音乐符号', 4, 28, 1571972262, 1),
       ('音乐常识', 4, 28, 1571972262, 1),

       -- 听辨
       ('单音', 4, 29, 1571972262, 1),
       ('音程', 4, 29, 1571972262, 1),
       ('节奏节拍', 4, 29, 1571972262, 1),
       ('旋律调式', 4, 29, 1571972262, 1),
       ('听觉分析', 4, 29, 1571972262, 1),

       -- 视唱
       ('视唱', 4, 30, 1571972262, 1),
       ('背唱', 4, 30, 1571972262, 1),

       -- 二级下分类
       -- 乐理
       ('音程', 4, 31, 1571972262, 1),
       ('和弦', 4, 31, 1571972262, 1),
       ('节奏节拍', 4, 31, 1571972262, 1),
       ('旋律调式', 4, 31, 1571972262, 1),
       ('音乐符号', 4, 31, 1571972262, 1),
       ('音乐常识', 4, 31, 1571972262, 1),

       -- 听辨题型
       ('单音', 4, 32, 1571972262, 1),
       ('音程', 4, 32, 1571972262, 1),
       ('和弦', 4, 32, 1571972262, 1),
       ('节奏节拍', 4, 32, 1571972262, 1),
       ('旋律调式', 4, 32, 1571972262, 1),
       ('听觉分析', 4, 32, 1571972262, 1),

       -- 视唱
       ('构唱', 4, 33, 1571972262, 1),
       ('视唱', 4, 33, 1571972262, 1),
       ('背唱', 4, 33, 1571972262, 1);

insert into dict (type, key_name, key_code, key_value)
values ('question_status', '题目状态', '1', '上线'),
       ('question_status', '题目状态', '2', '下线'),
       ('question_status', '题目状态', '3', '废弃'),
       ('question_template', '题目类型', '1', '点选类型'),
       ('question_template', '题目类型', '2', '敲击类型'),
       ('question_template', '题目类型', '3', '拖拽类型'),
       ('question_template', '题目类型', '4', '录音类型'),
       ('question_timer_type', '题目倒计时类型', '1', '进入题目开始倒计时'),
       ('question_timer_type', '题目倒计时类型', '2', '音频播放完后开始倒计时');

insert into privilege (name, uri, method, is_menu, menu_name, parent_id, unique_en_name, created_time)
values ('题库管理', 'question_manage', 'get', 1, '题库管理', 0, 'question_manage', 1572315610);

select @last_id := last_insert_id();

insert into privilege (name, uri, method, is_menu, menu_name, parent_id, unique_en_name, created_time)
values
       ('题目列表', '/org_web/question/list', 'get', 1, '题目列表', @last_id, 'question_list', 1572315610),
       ('添加编辑题目', '/org_web/question/add_edit', 'post', 0, '', 0, 'add_edit_question', 1572315610),
       ('题目详情', '/org_web/question/detail', 'get', 0, '', 0, 'question_detail', 1572315610),
       ('编辑题目状态', '/org_web/question/status', 'post', 0, '', 0, 'question_status', 1572315610),
       ('题目分类', '/org_web/question/catalog', 'get', 0, '', 0, 'question_catalog', 1572315610),

       ('问题标签', '/org_web/question_tag/add_edit', 'post', 0, '', 0, 'add_edit_question_tag', 1572315610),
       ('可用标签', '/org_web/question_tag/tags', 'get', 0, '', 0, 'question_tag_tags', 1572315610),

       ('百度文字转语音', '/api/baidu/audio_token', 'get', 0, '', 0, 'baidu_audio_token', 1572315610);
