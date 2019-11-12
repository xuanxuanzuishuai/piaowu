alter table org_account add column (type tinyint not null default '1' comment '账号类型，1琴房(1v1) 2音乐教室(集体课)');
alter table org_license add column (type tinyint not null default '1' comment '许可证类型，1琴房(1v1) 2集体课服务费 3 集体课学生数量');


insert into dict (type, key_name, key_code, key_value) values ('account_type', '账号类型', 1, '1V1'),
                                                             ('account_type', '账号类型', 2, '集体课'),
                                                             ('license_type', '许可证类型', 1, '1V1'),
                                                             ('license_type', '许可证类型', 2, '集体课服务费'),
                                                             ('license_type', '许可证类型', 3, '集体课学生数量');