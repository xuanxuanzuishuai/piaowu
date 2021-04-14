<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/30
 * Time: 3:32 PM
 */

namespace App\Services;


use App\Libs\AIPLCenter;
use App\Libs\AliOSS;
use App\Libs\OpernCenter;
use App\Libs\Valid;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssAiPlayRecordModel;
use App\Models\Dss\DssStudentModel;
use App\Libs\DictConstants;


class AIPlayRecordService
{
    const DEFAULT_APP_VER = '5.0.0';


    /**
     * 获取测评报告（分享）
     * @param $recordId
     * @return array|mixed
     */
    public static function getStudentAssessData($recordId)
    {
        if (empty($recordId)){
            return [];
        }

        $report = DssAiPlayRecordCHModel::getRecordIdInfo($recordId);
        if (empty($report)) {
            $report = [];
        }
        $student = DssStudentModel::getById($report['student_id']);
        if (!empty($student['thumb'])) {
            $report['thumb'] = AliOSS::replaceCdnDomainForDss($student["thumb"]);
        } else {
            $report['thumb'] = AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb'));
        }
        $report['name'] = $student['name'];
        $report['uuid'] = $student['uuid'];
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, self::DEFAULT_APP_VER);
        $res = $opn->lessonsByIds($report['lesson_id']);
        if (!empty($res) && $res['code'] == Valid::CODE_SUCCESS) {
            $lesson_name = $res['data'][0]['lesson_name'];
        } else {
            $lesson_name = '';
        }

        if ($report['input_type'] && $report['input_type'] == 1 && $report['student_id']) {
            $aiAudio = self::getAiAudio($report['student_id'], $recordId);
            if(!empty($aiAudio)) {
                $report['audio_url'] = $aiAudio;
            }
        }

        if ($report && $report['student_id']) {
            $report['replay_token'] = ShowMiniAppService::genStudentToken($report["student_id"]);
        }

        if (!empty($report['score_rank']) && $report['score_rank'] > 0 && $report['score_rank'] < 60 || $report['is_phrase'] == 1 || $report['hand'] != 3) {
            $report['score_rank'] = "0";
        }

        $report['lesson_name'] = $lesson_name;
        return empty($report) ? [] : $report;
    }

    /**
     * 获取某个ai_record_id对应的陪练数据
     * @param $studentId
     * @param $aiRecordId
     * @return array|bool|mixed
     */
    public static function getAiAudio($studentId, $aiRecordId)
    {
        if (empty($aiRecordId)) {
            return [];
        }
        $data = AIPLCenter::userAudio($aiRecordId);
        return $data['data']['audio_url'] ?? '';
    }
}