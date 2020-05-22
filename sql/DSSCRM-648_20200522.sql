insert into privilege (name, uri, method, is_menu, menu_name, parent_id, unique_en_name, created_time)
values ('练琴统计', '/org_web/play_record/statistics', 'get', 1, '练琴统计', 513, 'play_record_statistics', 1590076800);


ALTER TABLE `ai_play_record`
ADD INDEX `end_time` (`end_time` ASC);

ALTER TABLE `review_course_task`
ADD INDEX `student_id` (`student_id` ASC),
ADD INDEX `play_date` (`play_date` ASC);
