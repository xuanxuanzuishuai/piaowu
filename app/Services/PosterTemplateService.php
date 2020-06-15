<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/6/10
 * Time: 下午7:27
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\TemplatePosterModel;
use App\Models\TemplatePosterWordModel;
use App\Libs\Util;
use App\Libs\UserCenter;
use App\Models\UserQrTicketModel;
use App\Models\UserWeixinModel;
use App\Models\AIPlayRecordModel;
use App\Models\StudentModel;

class PosterTemplateService
{
    /**
     * @param $params
     * @param $operateId
     * @return bool
     * @throws RunTimeException
     * 添加数据
     */
    public static function addData($params, $operateId)
    {
        $time = time();
        $data = [
            'poster_name' => $params['poster_name'],
            'poster_url' => $params['poster_url'],
            'poster_status' => $params['poster_status'],
            'order_num' => $params['order_num'],
            'type' => $params['type'],
            'operate_id' => $operateId,
            'create_time' => $time,
            'update_time' => $time
        ];
        $res = TemplatePosterModel::insertRecord($data);
        if (empty($res)) {
            SimpleLogger::error('template poster add data fail', $data);
            throw new RunTimeException(['template_poster_add_data_fail']);
        }
        return true;
    }

    /**
     * @param $params
     * @return array
     * 处理模板图数据
     */
    public static function getList($params)
    {
        list($res, $pageId, $pageLimit, $totalCount) = TemplatePosterModel::getList($params);
        $data = [];
        if (!empty($res)) {
            foreach ($res as $k => $value) {
                $row = self::formatPosterInfo($value);
                $row['display_order_num'] = $value['order_num'];
                $data[] = $row;
            }
        }
        return [$data, $pageId, $pageLimit, $totalCount];
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
     * @param $posterId
     * @return array
     * 某条海报模板图信息
     */
    public static function getOnePosterInfo($posterId)
    {
        $info = TemplatePosterModel::getRecord(['id' => $posterId], []);
        return self::formatPosterInfo($info);
    }

    /**
     * @param $params
     * @param $operateId
     * @return mixed
     * 更新某条海报的信息
     */
    public static function editData($params, $operateId)
    {
        $needUpdate['update_time'] = time();
        $needUpdate['operate_id'] = $operateId;
        isset($params['poster_name']) && $needUpdate['poster_name'] = $params['poster_name'];
        isset($params['poster_url']) && $needUpdate['poster_url'] = $params['poster_url'];
        isset($params['poster_status']) && $needUpdate['poster_status'] = $params['poster_status'];
        isset($params['order_num']) && $needUpdate['order_num'] = $params['order_num'];
        TemplatePosterModel::updateRecord($params['poster_id'], $needUpdate);
        return $needUpdate;
    }

    /**
     * @param $params
     * @param $operateId
     * @return bool
     * @throws RunTimeException
     * 海报模板图文案添加
     */
    public static function addWordData($params, $operateId)
    {
        $time = time();
        $data = [
            'content' => Util::textEncode($params['content']),
            'status'  => $params['status'],
            'create_time' => $time,
            'update_time' => $time,
            'operate_id' => $operateId
        ];
        $res = TemplatePosterWordModel::insertRecord($data);
        if (empty($res)) {
            SimpleLogger::error('template poster word add data fail', $data);
            throw new RunTimeException(['template_poster_word_add_data_fail']);
        }
        return true;
    }

    /**
     * @param $params
     * @return array
     * 海报模板图文案列表
     */
    public static function getWordList($params)
    {
        list($res, $pageId, $pageLimit, $totalCount) = TemplatePosterWordModel::getList($params);
        $data = [];
        if (!empty($res)) {
            foreach ($res as $k => $value) {
                $data[] = self::formatWordInfo($value);
            }
        }
        return [$data, $pageId, $pageLimit, $totalCount];
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

    /**
     * @param $posterWordId
     * @return array
     * 获取某条文案信息
     */
    public static function getOnePosterWordInfo($posterWordId)
    {
        $info = TemplatePosterWordModel::getRecord(['id' => $posterWordId], []);
        return self::formatWordInfo($info);
    }

    /**
     * @param $params
     * @param $operateId
     * @return mixed
     * 更新海报文案信息
     */
    public static function editWordData($params, $operateId)
    {
        $needUpdate['update_time'] = time();
        $needUpdate['operate_id'] = $operateId;
        isset($params['content']) && $needUpdate['content'] = Util::textEncode($params['content']);
        isset($params['status']) && $needUpdate['status'] = $params['status'];
        TemplatePosterWordModel::updateRecord($params['poster_word_id'], $needUpdate);
        return $needUpdate;
    }

    /**
     * 海报模板列表
     * @param $studentId
     * @param $templateType
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function templatePosterList($studentId, $templateType)
    {
        //获取学生当前状态
        $studentDetail = StudentService::studentStatusCheck($studentId);
        $data = [
            'template_list' => [],
            'student_status' => '',
            'qr_url' => '',
            'poster_config' => [],
            'student_info' => [
                'play_days' => 0,
                'total_duration' => 0,
                'nickname' => '',
                'headimgurl' => '',
                'uuid' => $studentDetail['student_info']['uuid'] ,
            ],
        ];
        $data['student_status'] = $studentDetail['student_status'];
        if ($data['student_status'] == StudentModel::STATUS_UNBIND) {
            //未绑定
            return $data;
        }
        //获取海报模板数据
        $templateList = TemplatePosterModel::getRecords(
            [
                "poster_status" => TemplatePosterModel::NORMAL_STATUS,
                "type" => $templateType,
                "ORDER" => [
                    "order_num" => "ASC",
                    "create_time" => "DESC",
                ]
            ],
            [
                "poster_url",
                "poster_name",
            ],
            false
        );
        if (empty($templateList)) {
            return $data;
        }
        foreach ($templateList as $k => $value) {
            $row = self::formatPosterInfo($value);
            $data['template_list']['list'][] = $row;
        }
        //区分海报类型获取不同数据
        if ($templateType == TemplatePosterModel::INDIVIDUALITY_POSTER) {
            //用户微信昵称
            $studentOpenId = UserWeixinModel::getBoundUserIds([$studentId], UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT);
            if ($studentOpenId) {
                $userWeChatInfo = WeChatService::getUserInfo($studentOpenId[0]['open_id']);
                $data['student_info']['nickname'] = $userWeChatInfo['nickname'];
                $data['student_info']['headimgurl'] = $userWeChatInfo['headimgurl'];
            }
            //练琴数据
            $playRecord = AIPlayRecordModel::getStudentSumByDate($studentId, 0, time());
            if ($playRecord) {
                $data['student_info']['play_days'] = count($playRecord);
                $data['student_info']['total_duration'] = ceil(array_sum(array_column($playRecord, 'sum_duration')) / 60);
            }
            //个性化渠道ID
            $channelId = DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'POSTER_LANDING_49_STUDENT_INVITE_STUDENT');
        } else {
            //标准海报渠道ID
            $channelId = DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'NORMAL_STUDENT_INVITE_STUDENT');
        }
        //49landing页跳转二维码
        $landingQrRes = UserService::getUserQRAliOss($studentId, UserQrTicketModel::STUDENT_TYPE, $channelId);
        if ($landingQrRes['qr_url']) {
            $data['qr_url'] = AliOSS::signUrls($landingQrRes['qr_url']);
        }
        //获取海报/二维码宽高配置数据
        $data['poster_config'] = DictConstants::getSet(DictConstants::TEMPLATE_POSTER_CONFIG);
        //返回数据
        return $data;
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
            "status" => TemplatePosterWordModel::NORMAL_STATUS
        ];
        $startCount = ($params['page'] - 1) * $params['count'];
        $dataCount = TemplatePosterWordModel::getCount($where);
        $list['count'] = $dataCount;
        if (empty($dataCount) || ($startCount > $dataCount)) {
            return $list;
        }
        $where['LIMIT'] = [$startCount, $params['count']];
        $data = TemplatePosterWordModel::getRecords($where, ['content', 'id'], false);
        foreach ($data as $k => $value) {
            $row = self::formatWordInfo($value);
            $list['list'][] = $row;
        }
        //返回数据
        return $list;
    }
}