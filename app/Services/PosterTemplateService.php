<?php
namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityPosterModel;
use App\Models\ActivityExtModel;
use App\Models\CHModel\AprViewStudentModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssTemplatePosterModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\PosterModel;
use App\Models\ShareMaterialConfig;
use App\Models\TemplatePosterModel;
use App\Models\TemplatePosterWordModel;
use I18N\Lang;

class PosterTemplateService
{
    // 预生成小程序码标记
    const KEY_PRE_GENERATE_CODE = 'pre_generate_code_';
    const KEY_PRE_GENERATE_CODE_LOCK = 'pre_generate_code_lock_';

    //参与类型 周周或月月
    protected static $typeArray = [
        1 => 'month',
        2 => 'week',
    ];

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
//        if ($templateType == TemplatePosterWordModel::INDIVIDUALITY_POSTER) {
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
        if (isset($row['name'])) {
            $row['poster_name'] = $row['name'];
        }
        if (isset($row['poster_status'])) {
            $row['poster_status_zh'] = DictConstants::get(DictConstants::SHARE_POSTER_CHECK_STATUS, $row['poster_status']);
        }
        if (isset($row['status'])) {
            $row['poster_status'] = $row['status'];
            $row['poster_status_zh'] = DictConstants::get(DictConstants::SHARE_POSTER_CHECK_STATUS, $row['status']);
        }
        if (isset($row['update_time'])) {
            $row['update_time'] = date('Y-m-d H:i', $row['update_time']);
        }
        if (isset($row['operator_name'])) {
            $row['operator_name'] = $row['operator_name'] ?? '';
        }
        if (!empty($row['poster_path'])) {
            $row['poster_url'] = AliOSS::replaceCdnDomainForDss($row['poster_path']);
        }
        if (!empty($row['example_path'])) {
            $row['example_url'] = AliOSS::replaceCdnDomainForDss($row['example_path']);
        }
        return $row;
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
        $data = TemplatePosterWordModel::getRecords($where, ['content', 'id']);
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
        if (isset($row['practise'])) {
            $row['practise_zh'] = $row['practise'] == TemplatePosterModel::PRACTISE_WANT ? '是' : '否';
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
                $row['poster_type'] = TemplatePosterModel::STANDARD_POSTER_TXT;
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
            'practise' => $params['practise'] ?? TemplatePosterModel::PRACTISE_NOT_WANT,
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
     * 下线某条海报前校验
     * @param $params
     * @return mixed
     * @throws RunTimeException
     */
    public static function offlinePosterCheck($params)
    {
        $id = $params['id'];
        list($resWeek, $resMonth, $resRt) = TemplatePosterModel::getActivityByPosterId($id);
        $arrWeekId = array_values(array_unique(array_column($resWeek, 'activity_id')));
        $arrMonthId = array_values(array_unique(array_column($resMonth, 'activity_id')));
        $resRtId = array_values(array_unique(array_column($resRt, 'activity_id')));
        //金叶子商城分享配置-校验
        $conds = [
            'material_id' => $id,
            'type'        => ShareMaterialConfig::POSTER_TYPE,
            'status'      => ShareMaterialConfig::NORMAL_STATUS
        ];
        $shareConfigId = ShareMaterialConfig::getRecord($conds, ['share_config_id']);
        return [$arrWeekId, $arrMonthId, $resRtId, $shareConfigId];
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
        isset($params['practise']) && $needUpdate['practise'] = $params['practise'];

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

        //更新activity_poster表数据
        if (isset($params['status'])) {
            $id = $params['id'];
            $statusMap = [
                TemplatePosterModel::DISABLE_STATUS => ActivityPosterModel::DISABLE_STATUS,
                TemplatePosterModel::NORMAL_STATUS => ActivityPosterModel::NORMAL_STATUS,
            ];
            if (isset($statusMap[$params['status']])) {
                ActivityPosterModel::editPosterStatus($id, $statusMap[$params['status']]);
                ActivityPosterModel::delRedisCache($id);
                //更新share_material_config表数据
                ShareMaterialConfig::batchUpdateRecord(['show_status' => $params['status']], ['material_id' => $id, 'type' => ShareMaterialConfig::POSTER_TYPE]);
            }
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
        //清除前端展示文案缓存
        TemplatePosterWordModel::delWordListCache();
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
        //更新share_material_config表数据
        ShareMaterialConfig::batchUpdateRecord(['show_status' => $params['status']], ['material_id' => $params['id'], 'type' => ShareMaterialConfig::POSTER_WORD_TYPE]);
        //清除前端展示文案缓存
        TemplatePosterWordModel::delWordListCache();
        return $needUpdate;
    }

    /**
     * 获取海报列表
     * @param $studentId
     * @param $type
     * @param int $activityId
     * @param array $ext
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function getPosterList($studentId, $type, $activityId = 0, $ext = [])
    {
        if (!in_array($type, array_keys(self::$typeArray))) {
            throw new RunTimeException(['invalid_data']);
        }
        $data = ['list' => [], 'activity' => []];
        // 查询活动：
        $activityInfo = ActivityService::getByTypeAndId($type, $activityId);
        if (empty($activityInfo)) {
            return $data;
        }
        $posterConfig = PosterService::getPosterConfig();
        $userDetail = StudentService::dssStudentStatusCheck($studentId, false, null);
        $userInfo = [
            'nickname' => $userDetail['student_info']['name'] ?? '',
            'headimgurl' => StudentService::getStudentThumb($userDetail['student_info']['thumb'])
        ];

        //练琴数据
        $practise = AprViewStudentModel::getStudentTotalSum($studentId);

        // 查询活动对应海报
        $posterList = PosterService::getActivityPosterList($activityInfo);
        if (empty($posterList)) {
            return $data;
        }
        $typeColumn             = array_column($posterList, 'type');
        $activityPosteridColumn = array_column($posterList, 'activity_poster_id');
        //周周领奖 海报排序处理
        if ($activityInfo['poster_order'] == TemplatePosterModel::POSTER_ORDER) {
            array_multisort($typeColumn, SORT_DESC, $activityPosteridColumn, SORT_ASC, $posterList);
        }
        $channel = self::getChannel($type, $ext['from_type']);
        $extParams = [
            'user_current_status' => $userDetail['student_status'] ?? 0,
            'activity_id' => $activityInfo['activity_id'],
        ];

        $userQrParams = [];
        foreach ($posterList as &$item) {
            $_tmp = $extParams;
            $_tmp['poster_id'] = $item['poster_id'];
            $_tmp['user_id'] = $studentId;
            $_tmp['user_type'] = DssUserQrTicketModel::STUDENT_TYPE;
            $_tmp['channel_id'] = $channel;
            $_tmp['landing_type'] = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
            $_tmp['qr_sign'] = QrInfoService::createQrSign($_tmp);
            $userQrParams[] = $_tmp;

            $item['qr_sign'] = $_tmp['qr_sign'];
        }
        unset($item);
        $userQrArr = MiniAppQrService::getUserMiniAppQrList($userQrParams);

        foreach ($posterList as &$item) {
            $extParams['poster_id'] = $item['poster_id'];
            $item = self::formatPosterInfo($item);
            if ($item['type'] == TemplatePosterModel::INDIVIDUALITY_POSTER) {
                $item['qr_code_url'] = AliOSS::replaceCdnDomainForDss($userQrArr[$item['qr_sign']]['qr_path']);
                continue;
            }
            // 海报图：
            $poster = PosterService::generateQRPoster(
                $item['poster_path'],
                $posterConfig,
                $studentId,
                DssUserQrTicketModel::STUDENT_TYPE,
                $channel,
                $extParams,
                $userQrArr[$item['qr_sign']] ?? []
            );
            $item['poster_url'] = $poster['poster_save_full_path'];
        }
        $activityInfo['ext'] = ActivityExtModel::getActivityExt($activityInfo['activity_id']);
        // 周周领奖限制检测
        $canUploadFlag = true;
        if ($type == TemplatePosterModel::STANDARD_POSTER) {
            if ($userDetail['student_status'] != DssStudentModel::STATUS_BUY_NORMAL_COURSE) {
                $activityInfo['error'] = Lang::getWord('only_year_user_enter_event');
            }
            // 所有活动列表
            $activityList = ActivityService::getWeekActivityList(['user_info' => ['user_id' => $studentId]]);
            // 无活动可用，不可上传
            if (!$activityList['available']) {
                $canUploadFlag = false;
            }
        }
        $data['list'] = $posterList;
        $data['activity'] = $activityInfo;
        $data['student_info'] = $userInfo;
        $data['student_status'] = $userDetail['student_status'];
        $data['student_status_zh'] = DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$userDetail['student_status']] ?? DssStudentModel::STATUS_REGISTER;
        $data['can_upload'] = $canUploadFlag;
        $data['practise'] = $practise;
        $data['uuid'] = $userDetail['student_info']['uuid'];
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
        return DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'CHANNEL_STANDARD_POSTER');
    }

    /**
     * 预生成小程序码
     * @param string $openId
     * @param int $userId
     * @return bool
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function preGenQRCode($openId = '', $userId = 0)
    {
        if (empty($openId) && empty($userId)) {
            return false;
        }
        if (!empty($openId)) {
            $user = DssUserWeiXinModel::getByOpenId($openId);
        }
        if (!empty($userId)) {
            $user = DssStudentModel::getByid($userId);
            $user['user_id'] = $user['id'] ?? 0;
        }
        if (empty($user['user_id'])) {
            return false;
        }
        $redis   = RedisDB::getConn();
        $lockKey = self::KEY_PRE_GENERATE_CODE_LOCK . $user['user_id'];
        $lock = $redis->set($lockKey, time(), 'EX', 60, 'NX');
        if (empty($lock)) {
            return false;
        }

        $cacheKey = self::KEY_PRE_GENERATE_CODE . $user['user_id'];
        $cache    = $redis->get($cacheKey);
        if (!empty($cache)) {
            return false;
        }
        $userDetail = StudentService::dssStudentStatusCheck($user['user_id'], false, null);
        $extParams  = ['user_current_status' => $userDetail['student_status'] ?? 0];
        $typeList   = [TemplatePosterModel::STANDARD_POSTER, TemplatePosterModel::INDIVIDUALITY_POSTER];
        foreach ($typeList as $type) {
            $channelId    = self::getChannelByType($type);
            $activityInfo = ActivityService::getByTypeAndId($type);
            if (empty($activityInfo)) {
                continue;
            }
            $extParams['a'] = $activityInfo['activity_id'];
            $posterList     = PosterService::getActivityPosterList($activityInfo);
            foreach ($posterList as $poster) {
                $extParams['p'] = $poster['poster_id'];
                DssUserQrTicketModel::getUserQrURL(
                    $user['user_id'],
                    DssUserWeiXinModel::USER_TYPE_STUDENT,
                    $channelId,
                    DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
                    $extParams
                );
            }
        }
        $redis->setex($cacheKey, Util::TIMESTAMP_1H, time());
        return true;
    }

    /**
     * 海报文案下线前校验
     * @param $request
     * @return mixed
     */
    public static function offlineWordListCheck($request)
    {
        $id = $request['id'];
        //金叶子商城分享配置校验
        $conds = [
            'material_id' => $id,
            'type'        => ShareMaterialConfig::POSTER_WORD_TYPE,
            'status'      => ShareMaterialConfig::NORMAL_STATUS
        ];
        $shareConfigId = ShareMaterialConfig::getRecord($conds, ['share_config_id']);
        return $shareConfigId;
    }

    /**
     * 获取渠道
     * @param $type
     * @param $fromType
     * @return array|mixed|null
     */
    public static function getChannel($type, $fromType)
    {
        $type = self::$typeArray[$type];
        return DictConstants::get(DictConstants::ACTIVITY_CONFIG, sprintf('channel_%s_%s', $type, $fromType));
    }
}