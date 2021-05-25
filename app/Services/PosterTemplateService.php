<?php
namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssTemplatePosterModel;
use App\Models\Dss\DssTemplatePosterWordModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\PosterModel;
use App\Models\TemplatePosterModel;
use App\Models\TemplatePosterWordModel;
use I18N\Lang;

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
        $data['student_status_zh'] = DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$studentDetail['student_status']] ?? DssStudentModel::STATUS_REGISTER;
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
        if (isset($row['name'])) {
            $formatData['poster_name'] = $row['name'];
        }
        if (isset($row['poster_url'])) {
            $formatData['poster_url'] = $row['poster_url'];
            $formatData['full_poster_url'] = AliOSS::signUrls($row['poster_url']);
        }
        if (isset($row['poster_path'])) {
            $formatData['poster_path'] = $row['poster_path'];
            $formatData['full_poster_url'] = AliOSS::signUrls($row['poster_path']);
        }
        if (isset($row['example_url'])) {
            $formatData['example_url'] = $row['example_url'];
            $formatData['full_example_url'] = AliOSS::signUrls($row['example_url']);
        }
        if (isset($row['example_path'])) {
            $formatData['example_path'] = $row['example_path'];
            $formatData['full_example_url'] = AliOSS::signUrls($row['example_path']);
        }
        if (isset($row['poster_status'])) {
            $formatData['poster_status'] = $row['poster_status'];
            $formatData['poster_status_zh'] = DictConstants::get(DictConstants::SHARE_POSTER_CHECK_STATUS, $row['poster_status']);
        }
        if (isset($row['status'])) {
            $formatData['poster_status'] = $row['status'];
            $formatData['poster_status_zh'] = DictConstants::get(DictConstants::SHARE_POSTER_CHECK_STATUS, $row['status']);
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
        if (isset($row['op_poster_id'])) {
            $formatData['op_poster_id'] = $row['op_poster_id'];
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

    /**
     * @param $row
     * @return array
     * 格式化某一条的信息
     */
    private static function formatOpPosterInfo($row)
    {
        if (isset($row['poster_path'])) {
            $row['poster_url'] = AliOSS::signUrls($row['poster_path']);
        }
        if (isset($row['example_path'])) {
            $row['example_url'] = AliOSS::signUrls($row['example_path']);
        }
        if (isset($row['status'])) {
            $row['status_zh'] = DictConstants::get(DictConstants::TEMPLATE_POSTER_CONFIG, $row['status']);
        }
        if (isset($row['update_time'])) {
            $row['update_time'] = date('Y-m-d H:i', $row['update_time']);
        }
        return $row;
    }
    
    /**
     * @param $row
     * @return array
     * 格式化模板图文案信息
     */
    private static function formatOpWordInfo($row)
    {
        if (isset($row['content'])) {
            $row['content'] = Util::textDecode($row['content']);
        }
        if (isset($row['status'])) {
            $row['status_zh'] = DictConstants::get(DictConstants::TEMPLATE_POSTER_CONFIG, $row['status']);
        }
        if (isset($row['update_time'])) {
            $row['update_time'] = date('Y-m-d H:i', $row['update_time']);
        }
        return $row;
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
                $row = self::formatOpPosterInfo($value);
                $row['display_order_num'] = $value['order_num'];
                $data[] = $row;
            }
        }
        return [$data, $pageId, $pageLimit, $totalCount];
    }
    
    /**
     * @param $posterId
     * @return array
     * 某条海报模板图信息
     */
    public static function getOnePosterInfo($posterId)
    {
        $info = TemplatePosterModel::getRecord(['id' => $posterId]);
        return self::formatOpPosterInfo($info);
    }
    
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
            'name' => $params['name'],
            'poster_path' => $params['poster_path'],
            'example_path' => $params['example_path'],
            'status' => $params['status'],
            'order_num' => $params['order_num'],
            'type' => $params['type'],
            'operate_id' => $operateId,
            'create_time' => $time,
            'update_time' => $time,
        ];
        
        $posterPath = $params['poster_path'];
        $posterParams = [
            'path' => $posterPath,
            'name' => $params['name'],
        ];
        $posterId = PosterModel::getIdByPath($posterPath, $posterParams);
        $data['poster_id'] = $posterId;
        
        $examplePath = $params['example_path'];
        $exampleParams = [
            'path' => $posterPath,
            'name' => $params['name'],
        ];
        $exampleId = PosterModel::getIdByPath($examplePath, $exampleParams);
        $data['example_id'] = $exampleId;
        
        $res = TemplatePosterModel::insertRecord($data);
        if (empty($res)) {
            SimpleLogger::error('template poster add data fail', $data);
            throw new RunTimeException(['template_poster_add_data_fail']);
        }
        return true;
    }
    
    /**
     * 更新某条海报的信息
     * @param $params
     * @param $operateId
     * @return mixed
     * @throws RunTimeException
     */
    public static function editData($params, $operateId)
    {
        $needUpdate['update_time'] = time();
        $needUpdate['operate_id'] = $operateId;
        isset($params['name']) && $needUpdate['name'] = $params['name'];
        isset($params['poster_path']) && $needUpdate['poster_path'] = $params['poster_path'];
        isset($params['example_path']) && $needUpdate['example_path'] = $params['example_path'];
        isset($params['status']) && $needUpdate['status'] = $params['status'];
        isset($params['order_num']) && $needUpdate['order_num'] = $params['order_num'];
        
        if (isset($needUpdate['poster_path'])) {
            $posterPath = $params['poster_path'];
            $posterParams = [
                'path' => $posterPath,
                'name' => $params['name'],
            ];
            $posterId = PosterModel::getIdByPath($posterPath, $posterParams);
            if ($posterId <= 0) {
                throw new RunTimeException(['template_poster_add_data_fail'], ['path' => $params['poster_url'], 'name' => $params['poster_name']]);
            }
            $needUpdate['poster_id'] = $posterId;
        }
        
        if (isset($needUpdate['example_url'])) {
            $examplePath = $params['example_url'];
            $exampleParams = [
                'path' => $posterPath,
                'name' => $params['poster_name'],
            ];
            $exampleId = PosterModel::getIdByPath($examplePath, $exampleParams);
            if ($exampleId <= 0) {
                throw new RunTimeException(['template_poster_add_data_fail'], ['path' => $params['poster_url'], 'name' => $params['poster_name']]);
            }
            $needUpdate['example_id'] = $exampleId;
        }
        
        TemplatePosterModel::updateRecord($params['id'], $needUpdate);
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
                $data[] = self::formatOpWordInfo($value);
            }
        }
        return [$data, $pageId, $pageLimit, $totalCount];
    }
    
    /**
     * @param $posterWordId
     * @return array
     * 获取某条文案信息
     */
    public static function getOnePosterWordInfo($posterWordId)
    {
        $info = TemplatePosterWordModel::getRecord(['id' => $posterWordId], []);
        return self::formatOpWordInfo($info);
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
        TemplatePosterWordModel::updateRecord($params['id'], $needUpdate);
        return $needUpdate;
    }

    /**
     * 获取海报列表
     * @param $studentId
     * @param $type
     * @param int $activityId
     * @param false $withExt
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function getPosterList($studentId, $type, $activityId = 0, $withExt = false)
    {
        $data = ['list' => [], 'activity' => []];
        // 查询活动：
        $activityInfo = ActivityService::getByTypeAndId($type, $activityId);
        if (empty($activityInfo)) {
            return $data;
        }
        $posterConfig = PosterService::getPosterConfig();
        $userDetail = StudentService::dssStudentStatusCheck($studentId);

        // 查询活动对应海报
        $posterList = PosterService::getActivityPosterList($activityInfo);
        $channel = self::getChannelByType($type);
        foreach ($posterList as &$item) {
            $item = self::formatPosterInfo($item);
            $extParams = [
                'p' => $item['poster_id'],
                'user_current_status' => $userDetail['student_status'] ?? 0,
                'a' => $activityId,
            ];
            $poster = PosterService::generateQRPosterAliOss(
                $item['poster_path'],
                $posterConfig,
                $studentId,
                DssUserQrTicketModel::STUDENT_TYPE,
                $channel,
                $extParams
            );
            $item['poster_complete_example'] = $poster['poster_save_full_path'];
        }

        // 查询活动配置
        if ($withExt) {
            $activityInfo['ext'] = ActivityService::getActivityExt($activityInfo['activity_id']);
        }
        // 周周领奖限制检测
        if ($type == TemplatePosterModel::STANDARD_POSTER) {
            if ($userDetail['student_info']['has_review_course'] != DssStudentModel::REVIEW_COURSE_1980) {
                $activityInfo['error'] = Lang::getWord('only_year_user_enter_event');
            }
        }
        $data['list'] = $posterList;
        $data['activity'] = $activityInfo;
        return $data;
    }

    /**
     * 获取海报渠道
     * @param $type
     * @return array|mixed|null
     */
    private static function getChannelByType($type)
    {
        // 个性化海报渠道
        if ($type == DssTemplatePosterModel::INDIVIDUALITY_POSTER) {
            return DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'POSTER_LANDING_49_STUDENT_INVITE_STUDENT');
        }
        // 标准海报渠道ID
        return DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'APP_CAPSULE_INVITE_CHANNEL');
    }
}