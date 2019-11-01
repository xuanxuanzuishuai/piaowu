<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/23
 * Time: 上午11:29
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\QuestionModel;
use App\Models\QuestionTagRelationModel;

class QuestionService
{
    public static function addEdit($employeeId, $params)
    {
        if(empty($params['content_text']) && empty($params['content_img']) && empty($params['content_audio'])) {
            throw new RunTimeException(['text_img_audio_at_least_one_not_empty']);
        }

        //检查题干,文件，图片，音频至少一个不为空
        if(empty($params['content_text']) && empty($params['content_audio']) && empty($params['content_img'])) {
            throw new RunTimeException(['question_content_error']);
        }

        //音频播放和答题限制设置
        $audioSetObj = $params['audio_set'] ?? [];
        $audioSet = [
            'loop'          => intval($audioSetObj['loop'] ?? 1), // 播放遍数
            'has_timer'     => boolval($audioSetObj['has_timer'] ?? true), // 是否有倒计时
            'timer_type'    => intval($audioSetObj['timer_type'] ?? 1), // 倒计时类型
            'timer_seconds' => intval($audioSetObj['timer_seconds'] ?? 60), // 倒计时秒数
        ];

        //答案选项
        $optionsObj = $params['options'] ?? [];
        $options = [];
        $hasAnswer = false;
        $option = 0;
        foreach($optionsObj as $op) {
            $op = [
                'option'    => intval($op['option']),
                'img'       => strval($op['img']),
                'text'      => strval($op['text']),
                'is_answer' => boolval($op['is_answer'] ?? false),
            ];
            if(empty($op['text']) && empty($op['img'])) {
                throw new RunTimeException(['text_img_neither_empty']);
            }
            //答案不能超过一个
            if($op['is_answer']) {
                if($hasAnswer) {
                    throw new RunTimeException(['only_one_answer']);
                }
                $hasAnswer = true;
            }
            //选项必须从1开始，每次递增1
            if($op['option'] != $option + 1) {
                throw new RunTimeException(['option_error']);
            }
            $option ++;
            $options[] = $op;
        }
        //必须有一个答案
        if(!$hasAnswer) {
            throw new RunTimeException(['need_at_least_one_answer']);
        }
        //至少要有2个选项, 不能超过4个选项
        if(count($options) < 2 || count($options) > 4) {
            throw new RunTimeException(['options_must_between_2_4']);
        }

        //答案解析
        $answerExplainObj = $params['answer_explain'] ?? [];
        $answerExplain = [
            'text' => $answerExplainObj['text'],
            'img'  => $answerExplainObj['img'],
        ];

        $id = $params['id'];
        $data = [
            'exam_org'           => $params['exam_org'],
            'level'              => $params['level'],
            'catalog'            => $params['catalog'],
            'sub_catalog'        => $params['sub_catalog'],
            'template'           => $params['template'],
            'content_text'       => $params['content_text'],
            'content_img'        => $params['content_img'],
            'content_audio'      => $params['content_audio'],
            'content_text_audio' => $params['content_text_audio'],
            'audio_set'          => json_encode($audioSet, 1),
            'opern'              => $params['opern'],
            'options'            => json_encode($options, 1),
            'answer_explain'     => json_encode($answerExplain, 1),
            'status'             => $params['status'],
            'employee_id'        => $employeeId,
        ];

        if(empty($id)) {
            $data['create_time'] = time();
            $id = QuestionModel::insertRecord($data, false);
            if(empty($id)) {
                throw new RunTimeException(['save_fail']);
            }
        } else {
            $data['update_time'] = time();
            $affectedRows = QuestionModel::updateRecord($id, $data, false);
            if(empty($affectedRows)) {
                throw new RunTimeException(['update_fail']);
            }
        }

        if(!empty($params['question_tag'])) {
            QuestionTagRelationModel::updateStatusByQuestionId($id, Constants::STATUS_FALSE);

            $tags = array_unique($params['question_tag']);
            $tag = [];
            foreach($tags as $t) {
                $tag[] = [
                    'question_id'     => $id,
                    'question_tag_id' => $t,
                    'status'          => Constants::STATUS_TRUE,
                ];
            }
            $success = QuestionTagRelationModel::batchInsert($tag, false);
            if(!$success) {
                throw new RunTimeException(['save_tag_fail']);
            }
        }

        QuestionModel::delCacheQuestions();

        return $id;
    }

    public static function selectByPage($page, $count, $params)
    {
        list($records, $total) = QuestionModel::selectByPage($page, $count, $params);
        $catalogs = QuestionCatalogService::selectAll();
        $catalog = [];
        foreach($catalogs as $c) {
            $catalog[$c['id']] = $c['catalog'];
        }

        foreach($records as $k => $record) {
            $record['audio_set']      = json_decode($record['audio_set'], 1);
            $record['options']        = json_decode($record['options'], 1);
            $record['answer_explain'] = json_decode($record['answer_explain'], 1);
            $record['question_tag']   = empty($record['question_tag']) ? [] : explode(',', $record['question_tag']);

            $record['exam_org_zh']    = $catalog[$record['exam_org']];
            $record['level_zh']       = $catalog[$record['level']];
            $record['catalog_zh']     = $catalog[$record['catalog']];
            $record['sub_catalog_zh'] = $catalog[$record['sub_catalog']];

            $record['status_zh']   = DictConstants::get(DictConstants::QUESTION_STATUS, $record['status']);
            $record['template_zh'] = DictConstants::get(DictConstants::QUESTION_TEMPLATE, $record['template']);

            foreach($record['options'] as $kk => $v) {
                if(!empty($v['img'])) {
                    $v['whole_img'] = AliOSS::signUrls($v['img']);
                }
                $record['options'][$kk] = $v;
            }
            if(!empty($record['answer_explain']['img'])) {
                $record['answer_explain']['whole_img'] = AliOSS::signUrls($record['answer_explain']['img']);
            }
            if(!empty($record['content_img'])) {
                $record['whole_content_img'] = AliOSS::signUrls($record['content_img']);
            }
            if(!empty($record['content_audio'])) {
                $record['whole_content_audio'] = AliOSS::signUrls($record['content_audio']);
            }
            if(!empty($record['content_text_audio'])) {
                $record['whole_content_text_audio'] = AliOSS::signUrls($record['content_text_audio']);
            }

            $records[$k] = $record;
        }

        return [$records, $total];
    }

    public static function getById($id)
    {
        $record = QuestionModel::getByQuestionId($id);
        if(!empty($record)) {
            $record['audio_set']      = json_decode($record['audio_set'], 1);
            $record['options']        = json_decode($record['options'], 1);
            $record['answer_explain'] = json_decode($record['answer_explain'], 1);
            $record['question_tag']   = empty($record['question_tag']) ? [] : explode(',', $record['question_tag']);

            foreach($record['options'] as $k => $v) {
                if(!empty($v['img'])) {
                    $v['whole_img'] = AliOSS::signUrls($v['img']);
                }
                $record['options'][$k] = $v;
            }
            if(!empty($record['answer_explain']['img'])) {
                $record['answer_explain']['whole_img'] = AliOSS::signUrls($record['answer_explain']['img']);
            }
            if(!empty($record['content_img'])) {
                $record['whole_content_img'] = AliOSS::signUrls($record['content_img']);
            }
            if(!empty($record['content_audio'])) {
                $record['whole_content_audio'] = AliOSS::signUrls($record['content_audio']);
            }
            if(!empty($record['content_text_audio'])) {
                $record['whole_content_text_audio'] = AliOSS::signUrls($record['content_text_audio']);
            }
        }

        return $record;
    }

    public static function batchUpdateStatus($id, $status, $time)
    {
        $affectedRows = QuestionModel::batchUpdateRecord(
            ['status' => $status,
             'update_time' => $time],
            ['id' => $id],
            false);
        if(empty($affectedRows)) {
            throw new RunTimeException(['update_fail']);
        }

        QuestionModel::delCacheQuestions();

        return $affectedRows;
    }

    public static function questions()
    {
        return QuestionModel::questions();
    }

    //与getById不同的是，getByIdForApp是为小程序和app设计的，返回的数据的时候，需要把数字表示的列转为中文
    public static function getByIdForApp($id)
    {
        $record = QuestionModel::getByQuestionId($id);
        $catalogs = QuestionCatalogService::selectAll();
        $catalog = [];
        foreach($catalogs as $c) {
            $catalog[$c['id']] = $c['catalog'];
        }

        $record['audio_set']      = json_decode($record['audio_set'], 1);
        $record['options']        = json_decode($record['options'], 1);
        $record['answer_explain'] = json_decode($record['answer_explain'], 1);
        $record['question_tag']   = empty($record['question_tag']) ? [] : explode(',', $record['question_tag']);
        $record['exam_org_zh']    = $catalog[$record['exam_org']];
        $record['level_zh']       = $catalog[$record['level']];
        $record['catalog_zh']     = $catalog[$record['catalog']];
        $record['sub_catalog_zh'] = $catalog[$record['sub_catalog']];

        foreach($record['options'] as $k => $v) {
            if(!empty($v['img'])) {
                $v['img'] = AliOSS::signUrls($v['img']);
            }
            $record['options'][$k] = $v;
        }
        if(!empty($record['answer_explain']['img'])) {
            $record['answer_explain']['img'] = AliOSS::signUrls($record['answer_explain']['img']);
        }
        if(!empty($record['content_img'])) {
            $record['content_img'] = AliOSS::signUrls($record['content_img']);
        }
        if(!empty($record['content_audio'])) {
            $record['content_audio'] = AliOSS::signUrls($record['content_audio']);
        }
        if(!empty($record['content_text_audio'])) {
            $record['content_text_audio'] = AliOSS::signUrls($record['content_text_audio']);
        }

        return $record;
    }
}