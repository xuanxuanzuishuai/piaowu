<?php
namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\AutoReplyAnswerModel;
use App\Models\AutoReplyQuestionModel;

class AutoReplyService
{
    public static function addQuestion($title, $creatorId)
    {
        $question = self::getQuestionByTitleOrgWeb($title);
        if (!empty($question)) {
            throw new RunTimeException(['same_title_not_allowed']);
        }
        $insertData = [
            'title' => trim($title),
            'status' => Constants::STATUS_TRUE,
            'create_time' => time(),
            'creator_id' => $creatorId,
        ];
        $id = AutoReplyQuestionModel::insertRecord($insertData);
        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }
        return $id;
    }

    public static function editQuestion($id, $title, $status)
    {
        $updateData = [
            'title' => $title,
            'status' => $status,
        ];
        $QuestionRows = AutoReplyQuestionModel::updateRecord($id, $updateData);
         AutoReplyAnswerModel::batchUpdateRecord(['status' => $status], ['q_id' => $id]);
        if (empty($QuestionRows)) {
            throw new RunTimeException(['update_failure']);
        }
        return $id;
    }

    public static function questionOne($id)
    {
        //获取信息
        $question = AutoReplyQuestionModel::getRecord(['id' => $id]);
        if(empty($question)){
            return [];
        }
        $answer = AutoReplyAnswerModel::getRecords(['q_id' => $id]);
        if(empty($answer)){
            $data['question'] = $question;
            $data['answer'] = [];
            return $data;
        }
        foreach ($answer as $key => $value){
            if ($value['type'] == AutoReplyAnswerModel::AUTO_REPLAY_TYPE_IMAGE) {
                $value['answer'] = empty($value['answer']) ? '' : AliOSS::signUrls($value['answer']);
            }
            $answers[] = $value;
        }
        $data['question'] = $question;
        $data['answer'] = $answers ?? [];
        return $data;
    }

    public static function addAnswer($qId, $answer, $sort, $type)
    {
        $insertData = [
            'q_id' => $qId,
            'status' => Constants::STATUS_TRUE,
            'answer' => trim($answer),
            'sort' => $sort,
            'type' => $type,
        ];
        $id = AutoReplyAnswerModel::insertRecord($insertData);
        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }
        return $id;
    }

    public static function editAnswer($id, $qId, $status, $answer, $sort, $type)
    {
        $updateData = [
            'q_id' => $qId,
            'status' => $status,
            'answer' => $answer,
            'sort' => $sort,
            'type' => $type,
        ];
        $answerRows = AutoReplyAnswerModel::updateRecord($id, $updateData);
        if (empty($answerRows)) {
            throw new RunTimeException(['update_failure']);
        }
        return $id;
    }

    public static function answerOne($id)
    {
        //获取信息
        $answer = AutoReplyAnswerModel::getRecord(['id' => $id, 'status' => 1]);
        return $answer ?? [];
    }

    public static function getQuestionByTitle($title)
    {
        $questionWhere = [
            "status" => 1,
            "ORDER" => [
                "id" => "ASC"
            ]
        ];
        if(!empty($title)){
            $questionWhere['title'] = $title;
        }
        $questionWhere['LIMIT'] = 1;
        $answerWhere = [
            "status" => 1,
            "ORDER" => [
                "sort" => "ASC"
            ]
        ];
        $question = AutoReplyQuestionModel::getRecord($questionWhere);
        if(empty($question)){
            return [];
        }
        $answerWhere['q_id'] = $question['id'];
        $answer = AutoReplyAnswerModel::getRecords($answerWhere);
        $question['list'] = $answer;
        return $question ?? [];
    }

    public static function getQuestionList($key, $page, $count){
        $totalCount = AutoReplyQuestionModel::getTotalCount($key);
        if ($totalCount == 0) {
            return [[], 0];
        }
        $questionWhere = [
            "ORDER" => [
                "id" => "DESC"
            ]
        ];
        if(!empty($key)){
            $questionWhere['title[~]'] = Util::sqlLike($key);
        }
        $questionWhere['LIMIT'] = [($page - 1) * $count, $count];
        $question = AutoReplyQuestionModel::getRecords($questionWhere);
        if(empty($question)){
            return [[], 0];
        }
        return [$question, $totalCount];
    }

    public static function getQuestionByTitleOrgWeb($title)
    {
        $questionWhere = [
            "ORDER" => [
                "id" => "ASC"
            ]
        ];
        if(!empty($title)){
            $questionWhere['title'] = $title;
        }
        $questionWhere['LIMIT'] = 1;
        $question = AutoReplyQuestionModel::getRecord($questionWhere);
        return $question ?? [];
    }
}
