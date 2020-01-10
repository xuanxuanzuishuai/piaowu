set @parentMenuId = (select id from privilege where unique_en_name = 'review_course_menu');
INSERT INTO `privilege` (`name`, `uri`, `method`, `unique_en_name`, `parent_id`, `is_menu`, `menu_name`, `created_time`) VALUES
  ('点评任务列表', '/org_web/review_course/tasks', 'get', 'review_tasks', @parentMenuId, '1', '点评任务列表', 1578650064),
  ('点评课日报详情(上课)', '/org_web/review_course/student_report_detail_class', 'get', 'review_course_student_report_detail_class', '', '0', '', 1578649743),
  ('点评课配置', '/org_web/review_course/config', 'get', 'review_course_config', '', '0', '', 1578650379),
  ('点评课练琴详情', '/org_web/review_course/play_detail', 'get', 'review_course_play_detail', '', '0', '', 1578650456),
  ('点评课上传音频', '/org_web/review_course/upload_review_audio', 'post', 'review_course_upload_review_audio', '', '0', '', 1578651700),
  ('发送点评', '/org_web/review_course/send_review', 'post', 'review_course_send_review', '', '0', '', 1578651759);