# 旧练琴数据导入 play_record -> ai_play_record
INSERT INTO ai_play_record (
  student_id,lesson_id,score_id,record_id,
  is_phrase,phrase_id,practice_mode,hand,ui_entry,input_type,
  create_time,end_time,duration,audio_url,
  score_final,score_complete,score_pitch,score_rhythm,score_speed,score_speed_average
) SELECT
    student_id,lesson_id,0,ai_record_id,
    is_frag,lesson_sub_id,cfg_mode,(((cfg_hand - 1) + 2) % 3) + 1,IF(lesson_type, 2, 6),ai_type,
    created_time,created_time,duration,'',
    score,0,0,0,0,0
  FROM
    tmp_play_record;

# 旧练琴数据导入 play_class_record -> ai_play_record
INSERT INTO ai_play_record (
  student_id,lesson_id,score_id,record_id,
  is_phrase,phrase_id,practice_mode,hand,ui_entry,input_type,
  create_time,end_time,duration,audio_url,
  score_final,score_complete,score_pitch,score_rhythm,score_speed,score_speed_average
) SELECT
    student_id,lesson_id,0,best_record_id,
    0,0,1,3,5,1,
    start_time,start_time,duration,'',
    0,0,0,0,0,0
  FROM
    tmp_play_class_record;