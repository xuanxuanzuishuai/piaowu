ALTER TABLE `dss_pre`.`play_record`
  CHANGE COLUMN `ai_type` `ai_type` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'ai识别类型 1midi 2音频 3分手分段' ,
  ADD COLUMN `opern_id` INT NOT NULL DEFAULT 0 COMMENT '曲谱id' ,
  ADD COLUMN `is_frag` TINYINT NOT NULL DEFAULT 0 COMMENT '是否是片段' ,
  ADD COLUMN `cfg_hand` TINYINT NOT NULL DEFAULT 1 COMMENT '分手' ,
  ADD COLUMN `cfg_mode` TINYINT NOT NULL DEFAULT 1 COMMENT '练习模式 1正常(PK) 2识谱(跟弹) 3慢练' ,
  ADD COLUMN `frag_key` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '步骤名' ;

update play_record set ai_type = 1 where ai_type = 0 or ai_type = 3;