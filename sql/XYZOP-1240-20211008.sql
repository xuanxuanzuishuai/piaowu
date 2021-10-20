INSERT INTO dict (type, key_name, key_code, key_value, `desc`)
VALUES ('template_poster_config', '海报模板图配置', 'QR_ID_X', '530', '海报唯一码x轴偏移量'),
       ('template_poster_config', '海报模板图配置', 'QR_ID_Y', '30', '海报唯一码y轴偏移量'),
       ('template_poster_config', '海报模板图配置', 'QR_ID_SIZE', '35', '海报唯一码字体大小'),
       ('template_poster_config', '海报模板图配置', 'QR_ID_COLOR', 'F8F8FF', '海报唯一码颜色'),
       ('template_poster_config', '海报模板图配置', 'DATE_X', '580', '日期x轴偏移量'),
       ('template_poster_config', '海报模板图配置', 'DATE_Y', '1250', '日期y轴偏移量'),
       ('template_poster_config', '海报模板图配置', 'DATE_SIZE', '50', '日期字体大小'),
       ('template_poster_config', '海报模板图配置', 'DATE_COLOR', 'F8F8FF', '日期颜色');


INSERT INTO dict (type, key_name, key_code, key_value, `desc`)
VALUES ('share_poster_check_reason', '分享截图审核原因', '13', '海报生成和上传非同一用户', '打卡截图审核原因'),
       ('share_poster_check_reason', '分享截图审核原因', '14', '海报生成和上传非同一活动', '打卡截图审核原因'),
       ('share_poster_check_reason', '分享截图审核原因', '15', '作弊码已经被使用', '打卡截图审核原因'),
       ('share_poster_check_reason', '分享截图审核原因', '16', '作弊码识别失败', '打卡截图审核原因');

update dict set key_value = 'AAAAAAAA' where id = 1756;


alter table share_poster
    add column unique_code varchar(10) not null default '' comment '防作弊码' after points_award_id,
    add index idx_unique_code (unique_code);


alter table real_share_poster
    add column unique_code varchar(10) not null default '' comment '防作弊码' after ext,
    add index idx_unique_code (unique_code);

-- 清除dict缓存
del dict_list_template_poster_config;

del dict_list_share_poster_check_reason;

del dict_list_mini_app_qr;