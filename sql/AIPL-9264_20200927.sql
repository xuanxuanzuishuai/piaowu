select @event_task_id:=id from erp_event_task where name = "本日完成一节课";
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`)
VALUES ('credit_activity_config', '积分活动', 'attend_class_task_id', @event_task_id),