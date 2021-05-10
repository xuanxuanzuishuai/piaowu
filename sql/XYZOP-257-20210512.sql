INSERT INTO `privilege`(`name`, `created_time`, `method`, `is_menu`,`menu_name`,`parent_id`,`unique_en_name`) VALUE
('通用管理', '1620786955', 'get', 1, '通用管理',0,'basic');
select @last_id := last_insert_id();

set @pId_1 = (select id from privilege where `unique_en_name` = 'app_push_list');
set @pId_2 = (select id from privilege where `unique_en_name` = 'third_part_bill_list');
set @pId_3 = (select id from privilege where `unique_en_name` = 'batch_import_award_points');
update `privilege` set parent_id = @last_id  where id in(@pId_1,@pId_2,@pId_3);