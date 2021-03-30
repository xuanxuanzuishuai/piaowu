<?php
namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Util;
use App\Models\Dss\DssAiPlayRecordModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssTemplatePosterModel;
use App\Models\Dss\DssTemplatePosterWordModel;
use App\Models\Dss\DssUserQrTicketModel;

class PosterTemplateService
{
    /**
     * 海报模板列表
     * @param $studentId
     * @param $templateType
     * @param $activityId
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function templatePosterList($studentId, $templateType, $activityId = NULL)
    {
        //获取学生当前状态
        $studentDetail = StudentService::dssStudentStatusCheck($studentId);
        $data = [
            'template_list' => [],
            'student_status' => '',
            'student_info' => [
                'play_days' => 0,
                'total_duration' => 0,
                'nickname' => '',
                'headimgurl' => '',
                'uuid' => $studentDetail['student_info']['uuid'],
            ],
        ];
        $data['student_status'] = $studentDetail['student_status'];
        //用户系统昵称/头像
        $data['student_info']['nickname'] = $studentDetail['student_info']['name'];
        $data['student_info']['headimgurl'] = StudentService::getStudentThumb($studentDetail['student_info']['thumb']);
        //获取海报模板数据
        $templateList = DssTemplatePosterModel::getRecords(
            [
                "poster_status" => DssTemplatePosterModel::NORMAL_STATUS,
                "type" => $templateType,
                "ORDER" => [
                    "order_num" => "ASC",
                    "create_time" => "DESC",
                ],
                "LIMIT" => [0,1]
            ],
            [
                "poster_url",
                "poster_name",
                "example_url",
                "op_poster_id"
            ]
        );
        if (empty($templateList)) {
            return $data;
        }
//        //区分海报类型获取不同数据
//        if ($templateType == DssTemplatePosterWordModel::INDIVIDUALITY_POSTER) {
//            //练琴数据
//            $playRecord = DssAiPlayRecordModel::getStudentSumByDate($studentId, 0, time());
//            if ($playRecord) {
//                $data['student_info']['play_days'] = count($playRecord);
//                $data['student_info']['total_duration'] = ceil(array_sum(array_column($playRecord, 'sum_duration')) / 60);
//            }
//            //个性化渠道ID
//            $channelId = DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'POSTER_LANDING_49_STUDENT_INVITE_STUDENT');
//        } else {
            //标准海报渠道ID
            $channelId = DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'APP_CAPSULE_INVITE_CHANNEL');
//        }
       //获取海报/二维码宽高配置数据
        $posterConfig = DictConstants::getSet(DictConstants::TEMPLATE_POSTER_CONFIG);
        foreach ($templateList as $k => $value) {
            $qrExtentParams = ['a' => $activityId, 'user_current_status' => $studentDetail['student_status'], 'p' => $value['op_poster_id']];
            //转介绍码
            $qrImagePath = DssUserQrTicketModel::getUserQrURL($studentId, DssUserQrTicketModel::STUDENT_TYPE, $channelId, DssUserQrTicketModel::LANDING_TYPE_MINIAPP, $qrExtentParams);
            $row = self::formatPosterInfo($value);
            $row['poster_complete_example'] = ReferralActivityService::genEmployeePoster(
                $row['poster_url'],
                $posterConfig['POSTER_WIDTH'],
                $posterConfig['POSTER_HEIGHT'],
                $qrImagePath,
                $posterConfig['QR_WIDTH'],
                $posterConfig['QR_HEIGHT'],
                $posterConfig['QR_X'],
                $posterConfig['QR_Y'])['poster_save_full_path'];
            unset($row['poster_url']);
            unset($row['example_url']);
            $data['template_list']['list'][] = $row;
        }

        //返回数据
        return $data;
    }

    /**
     * @param $row
     * @return array
     * 格式化某一条的信息
     */
    private static function formatPosterInfo($row)
    {
        $formatData = [];
        if (isset($row['id'])) {
            $formatData['poster_id'] = $row['id'];
        }
        if (isset($row['poster_name'])) {
            $formatData['poster_name'] = $row['poster_name'];
        }
        if (isset($row['poster_url'])) {
            $formatData['poster_url'] = $row['poster_url'];
            $formatData['full_poster_url'] = AliOSS::signUrls($row['poster_url']);
        }
        if (isset($row['example_url'])) {
            $formatData['example_url'] = $row['example_url'];
            $formatData['full_example_url'] = AliOSS::signUrls($row['example_url']);
        }
        if (isset($row['poster_status'])) {
            $formatData['poster_status'] = $row['poster_status'];
            $formatData['poster_status_zh'] = DictConstants::get(DictConstants::TEMPLATE_POSTER_CONFIG, $row['poster_status']);
        }
        if (isset($row['update_time'])) {
            $formatData['update_time'] = date('Y-m-d H:i', $row['update_time']);
        }
        if (isset($row['operator_name'])) {
            $formatData['operator_name'] = $row['operator_name'] ?? '';
        }
        if (isset($row['order_num'])) {
            $formatData['order_num'] = $row['order_num'];
        }
        return $formatData;
    }

    /**
     * 获取模版分享语列表
     * @param $params
     * @return array
     */
    public static function templatePosterWordList($params)
    {
        //获取数据
        $list = [
            'count' => 0,
            'list' => [],
        ];
        $where = [
            "status" => DssTemplatePosterWordModel::NORMAL_STATUS
        ];
        $startCount = ($params['page'] - 1) * $params['count'];
        $dataCount = DssTemplatePosterWordModel::getCount($where);
        $list['count'] = $dataCount;
        if (empty($dataCount) || ($startCount > $dataCount)) {
            return $list;
        }
        $where['LIMIT'] = [$startCount, $params['count']];
        $data = DssTemplatePosterWordModel::getRecords($where, ['content', 'id']);
        foreach ($data as $k => $value) {
            $row = self::formatWordInfo($value);
            $list['list'][] = $row;
        }
        //返回数据
        return $list;
    }

    /**
     * @param $row
     * @return array
     * 格式化模板图文案信息
     */
    private static function formatWordInfo($row)
    {
        $formatData = [];
        if (isset($row['id'])) {
            $formatData['poster_word_id'] = $row['id'];
        }
        if (isset($row['content'])) {
            $formatData['content'] = Util::textDecode($row['content']);
        }
        if (isset($row['status'])) {
            $formatData['status'] = $row['status'];
            $formatData['status_zh'] = DictConstants::get(DictConstants::TEMPLATE_POSTER_CONFIG, $row['status']);
        }
        if (isset($row['update_time'])) {
            $formatData['update_time'] = date('Y-m-d H:i', $row['update_time']);
        }
        if (isset($row['operator_name'])) {
            $formatData['operator_name'] = $row['operator_name'] ?? '';
        }
        return $formatData;
    }
}