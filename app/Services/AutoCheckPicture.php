<?php


namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterModel;
use App\Models\RealWeekActivityModel;
use App\Models\SharePosterModel;
use App\Models\WeekActivityModel;

class AutoCheckPicture
{
    public static $redisExpire = 432000; // 12小时

    /**
     * 获取要审核的图片
     * @param $data
     * @return array|null
     */
    public static function getSharePosters($data)
    {
        $record = self::getSharePostersHistoryRecord($data);
        if (empty($record['result'])) {
            return null;
        }
        $result = $record['result'];
        switch ($data['app_id']) {
            case Constants::SMART_APP_ID: //智能陪练
                $checkActivityIdStr = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'week_activity_id_effect');
                //在指定ID的活动内，不校验活动状态
                if (empty($checkActivityIdStr) || !in_array($result['activity_id'],explode(',',$checkActivityIdStr))){
                    $activityInfo = WeekActivityModel::getRecord(['activity_id' => [$result['activity_id']]], ['id', 'enable_status', 'start_time']);
                    if (empty($activityInfo) || $activityInfo['enable_status'] != OperationActivityModel::ENABLE_STATUS_ON) {
                        SimpleLogger::error('not found activity', ['id' => $result['activity_id']]);
                        return null;
                    }
                }
                break;
            case Constants::REAL_APP_ID: //真人陪练
                // 获取活动信息
                $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $result['activity_id']], ['id', 'activity_id', 'enable_status', 'start_time']);
                // 检查活动是否启动，未启用不能自动审核
                if (empty($activityInfo) || $activityInfo['enable_status'] != OperationActivityModel::ENABLE_STATUS_ON) {
                    SimpleLogger::error('not found activity', ['id' => $result['activity_id']]);
                    return null;
                }
                break;
            default:
                SimpleLogger::error('app_id error', ['id' => $result['activity_id']]);
                return null;
        }
        $imagePath = AliOSS::replaceCdnDomainForDss($result['image_path']);
//        // 获取日期 - 月.日
//        $date = date("n.j", $activityInfo['start_time']);
//        // 获取日期 - 年-月-日
//        $activityDate = date("Y-m-d", $activityInfo['start_time']);
//        $redis = RedisDB::getConn();
//        $cacheKey = 'letterIden';
//        if (!$redis->hexists($cacheKey, $date)) {
//            $letterIden = self::transformDate($activityDate);
//            $redis->hset($cacheKey, $date, $letterIden);
//            $redis->expire($cacheKey, self::$redisExpire);
//        }
//        $letterIden = $redis->hget($cacheKey, $date);
//        return [$date, $letterIden, $imagePath];
        return $imagePath ?? '';
    }

    /**
     * 获取历史审核记录
     * @param $data
     * @return array|null
     */
    public static function getSharePostersHistoryRecord($data)
    {
        if (empty($data) || empty($data['id']) || empty($data['app_id'])) {
            return null;
        }
        switch ($data['app_id']) {
            case Constants::SMART_APP_ID: //智能陪练 类型为上传截图领奖且未审核的
                $result = SharePosterModel::getRecord(['id' => $data['id'],'type' => SharePosterModel::TYPE_WEEK_UPLOAD, 'verify_status' => SharePosterModel::VERIFY_STATUS_WAIT], ['student_id', 'activity_id', 'image_path']);
                break;
            case Constants::REAL_APP_ID: //真人陪练
                $result = RealSharePosterModel::getRecord(
                    [
                        'id'            => $data['id'],
                        'type'          => RealSharePosterModel::TYPE_WEEK_UPLOAD,
                        'verify_status' => RealSharePosterModel::VERIFY_STATUS_WAIT,
                    ],
                    ['student_id', 'activity_id', 'image_path']
                );
                break;
            default:
                break;
        }
        //未找到符合审核条件的图片
        if (empty($result)) {
            SimpleLogger::error('empty poster image', ['id' => $data['id']]);
            return null;
        }
        //查询本周活动是否有系统审核拒绝的
        $conds = [
            'student_id'    => $result['student_id'],
            'activity_id'   => $result['activity_id'],
            'type'          => SharePosterModel::TYPE_WEEK_UPLOAD,
            'verify_status' => SharePosterModel::VERIFY_STATUS_UNQUALIFIED,
            'verify_user'   => EmployeeModel::SYSTEM_EMPLOYEE_ID
        ];
        switch ($data['app_id']) {
            case Constants::SMART_APP_ID: //智能陪练
                $historyRecord = SharePosterModel::getRecord($conds, ['id']);
                break;
            case Constants::REAL_APP_ID: //真人陪练
                $historyRecord = RealSharePosterModel::getRecord($conds, ['id']);
                if (empty($historyRecord)) {
                    $historyRecord = null;
                }
                break;
            default:
                break;
        }

        return compact('result', 'historyRecord');
    }

    /**
     * 日期转换为对应标识
     * @param $date
     * @return string
     */
    public static function transformDate($date)
    {
        $dateArray  = explode('-', $date);
        $array      = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
        $letterIden = '';
        foreach ($dateArray as $key => $val) {
            switch ($key) {
                case 0:
                    $yearDate = str_split($val, 1);
                    foreach ($yearDate as $item) {
                        $letterIden .= $array[$item] ?? 'A';
                    }
                    break;
                case 1:
                    $month      = intval($val);
                    $letterIden .= $array[$month - 1] ?? 'A';
                    break;
                case 2:
                    $monDate    = date('Y-m', strtotime($date));
                    $monday     = Util::getMonday($monDate);
                    $monday     = array_flip($monday);
                    $mondayKey  = $monday[$date] ?? 0;
                    $letterIden .= $array[$mondayKey] ?? 'F';
                    break;
            }
        }
        return $letterIden;
    }

    /**
     * 智能陪练-审核后续处理
     * @param $data
     * @param $status
     * @param $errCode
     * @throws RunTimeException
     */
    public static function mindCheckSharePosters($data, $status, $errCode)
    {
        $poster_id  = $data['id'];
        $params['employee_id']  = EmployeeModel::SYSTEM_EMPLOYEE_ID;
        if ($status > 0) {
            //审核通过
            SharePosterService::approvalPoster([$poster_id], $params);
        } elseif (!empty($errCode)) {
            foreach ($errCode as $value){
                switch ($value) {
                    case SharePosterModel::SYSTEM_REFUSE_CODE_NEW: //未使用最新海报
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_CODE_NEW;
                        break;
                    case SharePosterModel::SYSTEM_REFUSE_CODE_TIME: //朋友圈保留时长不足12小时，请重新上传
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_CODE_TIME;
                        break;
                    case SharePosterModel::SYSTEM_REFUSE_CODE_GROUP: //分享分组可见
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_CODE_GROUP;
                        break;
                    case SharePosterModel::SYSTEM_REFUSE_CODE_FRIEND: //请发布到朋友圈并截取朋友圈照片
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_CODE_FRIEND ;
                        break;
                    case SharePosterModel::SYSTEM_REFUSE_CODE_UPLOAD: //上传截图出错
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_CODE_UPLOAD;
                        break;
                    case SharePosterModel::SYSTEM_REFUSE_CODE_USER: //海报生成和上传非同一用户
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_CODE_USER;
                        break;
                    case SharePosterModel::SYSTEM_REFUSE_CODE_ACTIVITY_ID: //海报生成和上传非同一活动
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_CODE_ACTIVITY_ID;
                        break;
                    case SharePosterModel::SYSTEM_REFUSE_CODE_UNIQUE_USED: //作弊码已经被使用
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_UNIQUE_USED;
                        break;
                    case SharePosterModel::SYSTEM_REFUSE_CODE_COMMENT: //分享无分享语
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_CODE_COMMENT;
                        break;
                    case SharePosterModel::SYSTEM_REFUSE_CODE_UNIQUE: //作弊码识别失败
                        $params['reason'][] = SharePosterModel::SYSTEM_REFUSE_REASON_CODE_UNIQUE;
                        break;
                    default:
                        break;
                }
            }
            //审核拒绝
            SharePosterService::refusedPoster($poster_id, $params, SharePosterModel::VERIFY_STATUS_WAIT);
        }
    }

    /**
     * 真人陪练-审核后续处理-OP系统
     * @param $data
     * @param $status
     * @param $errCode
     * @throws RunTimeException
     */
    public static function realCheckSharePosters($data, $status,$errCode)
    {
        $poster_id  = $data['id'];
        $params['employee_id']  = EmployeeModel::SYSTEM_EMPLOYEE_ID;
        if ($status > 0) {
            //审核通过
            RealSharePosterService::approvalPoster([$poster_id], $params);
        } elseif (!empty($errCode)) {
            foreach ($errCode as $value){
                switch ($value) {
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_NEW: //未使用最新海报
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_CODE_NEW;
                        break;
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_TIME: //朋友圈保留时长不足12小时，请重新上传
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_CODE_TIME;
                        break;
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_GROUP: //分享分组可见
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_CODE_GROUP;
                        break;
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_FRIEND: //请发布到朋友圈并截取朋友圈照片
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_CODE_FRIEND;
                        break;
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_UPLOAD: //上传截图出错
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_CODE_UPLOAD;
                        break;
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_USER: //海报生成和上传非同一用户
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_CODE_USER;
                        break;
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_ACTIVITY_ID: //海报生成和上传非同一活动
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_CODE_ACTIVITY_ID;
                        break;
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_UNIQUE_USED: //作弊码已经被使用
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_UNIQUE_USED;
                        break;
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_COMMENT: //分享无分享语
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_CODE_COMMENT;
                        break;
                    case RealSharePosterModel::SYSTEM_REFUSE_CODE_UNIQUE: //作弊码识别失败
                        $params['reason'][] = RealSharePosterModel::SYSTEM_REFUSE_REASON_CODE_UNIQUE;
                        break;
                    default:
                        break;
                }
            }
            //审核拒绝
            RealSharePosterService::refusedPoster($poster_id, $params, SharePosterModel::VERIFY_STATUS_WAIT);
        }
    }

    /**
     * ocr审核海报
     * @param $imagePath
     * @param $msgBody
     * @return array|false
     */
    public static function checkByOcr($imagePath, $msgBody)
    {
        $status = 0; //默认审核失败
        //获取OCR识别内容
        $response = self::getOcrContent($imagePath);

        //针对纯图片 返回值特殊处理
        if (empty($response['ret'])) {
            $errCode[] = -5;
            return [$status, $errCode];
        }

        $hours          = 3600 * 12; //12小时
        $screenDate     = null; //截图时间初始化
        $uploadTime     = time(); //上传时间
        $contentKeyword = ['小叶子', '琴', '练琴', '很棒', '求赞']; //内容关键字
        $dateKeyword    = ['年', '月', '日', '昨天', '天前', '小时前', '分钟前', '上午', '：']; //日期关键字

        $shareType      = false; //分享-类型为朋友圈
        $shareKeyword   = false; //分享-关键字存在
        $shareOwner     = false; //分享-自己朋友圈
        $shareDate      = false; //分享-日期超过12小时
        $shareDisplay   = true;  //分享-是否显示
        $shareIden      = false; //分享-海报底部字母标识
        $leafKeyWord    = false; //分享-小叶子关键字
        $gobalIssetDel  = false; //分享-全局存在删除
        $issetDate      = false; //分享-全局存在时间
        $isSameUser     = false;  //海报合成和上传是否是一个人
        $isSameActivity = false;  //海报合成和上传是否是同一活动
        $errCode        = [];

        foreach ($response['ret'] as $key => $val) {
            $issetDel = false; //是否包含有删除
            $word     = $val['word'];
            //1.判断是否分享到朋友圈
            if (!$shareType && ($word == '朋友圈' || $word == '详情') && $val['rect']['top'] < 200) {
                $shareType = true;
                continue;
            }

            //2.判断是否分享到自己朋友圈
            if (Util::sensitiveWordFilter(['删除', '册除'], $word) == true) {
                $issetDel      = true;
                $gobalIssetDel = true;
            }
            if ($issetDel && $val['rect']['top'] > 300) {
                //判定是否是自己朋友圈-是否有删除文案且距离顶部的高度大于海报高度(580)
                $shareOwner = true;
            }

            //3.小叶子关键字
            if (mb_strpos($word, '小叶子') !== false) {
                $leafKeyWord = true;
            }

            //4.判断是否存在内容关键字['小叶子', '琴', '练琴', '很棒', '求赞']
            if ((mb_strlen($word) > 10 && Util::sensitiveWordFilter($contentKeyword, $word) == true)) {
                $shareKeyword = true;
                continue;
            }

            //5.判断是否设置了私密照片
            if (mb_strpos($word, '私密照片') !== false) {
                $shareDisplay = false;
                continue;
            }

            //6.判断海报合成和上传是否为同一用户以及是否为同一活动
            if (preg_match("/^[a-zA-Z0-9\s]{8,10}$/", $word)) {
                $replaceMap = [
                    0   => 'O',
                    1   => 'I',
                    2   => 'Z',
                    5   => 'S',
                    7   => 'T',
                    8   => 'B',
                    ' ' => '',
                ];
                $word       = str_replace(array_keys($replaceMap), array_values($replaceMap), strtoupper($word));
                $res        = preg_match("/^[A-Z]{8}$/", $word);
                if (!$res) {
                    continue;
                }
                $shareIden            = true;
                $composeInfo          = MiniAppQrService::getQrInfoById($word);
                $composeUser          = $composeInfo['user_id'] ?? 0;
                $composeCheckActivity = $composeInfo['check_active_id'] ?? 0;

                if ($msgBody['app_id'] == Constants::REAL_APP_ID) {
                    $uploadInfo = RealSharePosterModel::getRecord(['id' => $msgBody['id']], ['student_id', 'activity_id']);
                    $exist = RealSharePosterModel::getRecord(['unique_code' => $word, 'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED], ['id']);
                    if (empty($exist)) {
                        RealSharePosterModel::updateRecord($msgBody['id'], ['unique_code' => $word]);
                    } else {
                        $errCode[] = -8;
                    }
                } elseif ($msgBody['app_id'] == Constants::SMART_APP_ID) {
                    $uploadInfo = SharePosterModel::getRecord(['id' => $msgBody['id']], ['student_id', 'activity_id']);
                    $exist = SharePosterModel::getRecord(['unique_code' => $word, 'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED], ['id']);
                    if (empty($exist)) {
                        SharePosterModel::updateRecord($msgBody['id'], ['unique_code' => $word]);
                    } else {
                        $errCode[] = -8;
                    }
                }

                if (!empty($uploadInfo['student_id']) && $composeUser == $uploadInfo['student_id']) {
                    $isSameUser = true;
                }

                $checkActivityIdStr = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'week_activity_id_effect');
                $checkActivityIdArr = [];
                if (!empty($checkActivityIdStr)) {
                    $checkActivityIdArr = explode(',', $checkActivityIdStr);
                }
                if (!empty($uploadInfo['activity_id'])) {
                    //两个通过条件：一、海报生成和上传是一期活动/二、海报生成和上传在指定活动内
                    if ((in_array($composeCheckActivity, $checkActivityIdArr) && in_array($uploadInfo['activity_id'], $checkActivityIdArr)) || $composeCheckActivity == $uploadInfo['activity_id']) {
                        $isSameActivity = true;
                    }
                }
                continue;
            }

            //上传时间处理 字符串||关键字之后 ['年', '月', '日', '昨天', '天前', '小时前', '分钟前','上午', '：']
            if (Util::sensitiveWordFilter($dateKeyword, $word) == true && $val['rect']['top'] > 300) {
                //如果包含年月
                if (Util::sensitiveWordFilter(['年', '月', '日'], $word) == true) {
                    if (mb_strpos($word, '年') === false) {
                        continue;
                    }
                    if (mb_strpos($word, '月') === false) {
                        continue;
                    }
                    if (mb_strpos($word, '日') === false) {
                        continue;
                    }
                }

                $issetDate = true;
                //判定是否被屏蔽
                $last_date_word = mb_substr($word, mb_strlen($word) - 1);
                $nextWord       = $response['ret'][$key + 1]['word'];
                if (!$shareOwner && isset($response['ret'][$key + 1])) {
                    //发朋友圈时间下一个识别字段不含以下信息，判定被屏蔽
                    $condition_v1 = Util::sensitiveWordFilter(['删除', '智能陪练', '：', '册', '删', '1', $last_date_word, '除'], $nextWord) == false;

                    //发朋友圈时间下一个识别字段包含以下信息，判定被屏蔽
                    $condition_v2 = Util::sensitiveWordFilter(['.'], $nextWord) == true;
                    if ($condition_v1 || $condition_v2) {
                        $shareDisplay = false;
                    }
                }

                if (mb_strpos($word, '分钟前') !== false) {
                    continue;
                }

                if (mb_strpos($word, '天前') !== false) {
                    $shareDate = true;
                    continue;
                }

                if (mb_strpos($word, '：') !== false && mb_strlen($word) == 5) {
                    $word_str = str_replace('：', 0, $word);
                    if (!is_numeric($word_str)) {
                        continue;
                    }
                    $screenDate = date('Y-m-d ' . str_replace('：', ':', $word));//截图时间
                } elseif (mb_strpos($word, '小时前') !== false) {
                    $endWord    = '小时前';
                    $start      = 0;
                    $end        = mb_strpos($word, $endWord) - $start;
                    $string     = mb_substr($word, $start, $end);
                    $screenDate = date('Y-m-d H:i', strtotime('-' . $string . ' hours'));
                } elseif (Util::sensitiveWordFilter(['昨天', '年'], $word) === false && mb_strpos($word, '上午') !== false) { //当做今天的 下午可忽略
                    $beginWord  = '上午';
                    $endWord    = '删除';
                    $start      = mb_strpos($word, $beginWord) + mb_strlen($beginWord);
                    $end        = $issetDel ? (mb_strpos($word, $endWord) - $start) : mb_strlen($word) - 1;
                    $string     = mb_substr($word, $start, $end);
                    $screenDate = date('Y-m-d ' . str_replace('：', ':', $string));//截图时间
                } elseif (mb_strpos($word, '昨天') !== false) {
                    if (mb_strlen($word) == 2) {
                        $screenDate = date('Y-m-d', strtotime('-1 day'));
                    } elseif (mb_strpos($word, '上午') !== false || mb_strpos($word, '凌晨') !== false) {
                        $beginWord  = '昨天上午';
                        $endWord    = '删除';
                        $start      = mb_strpos($word, $beginWord) + mb_strlen($beginWord);
                        $end        = $issetDel ? (mb_strpos($word, $endWord) - $start) : mb_strlen($word) - 1;
                        $string     = mb_substr($word, $start, $end);
                        $screenDate = date('Y-m-d ' . str_replace('：', ':', $string), strtotime('-1 day'));//截图时间
                    } elseif (mb_strpos($word, '下午') !== false) {
                        $beginWord  = '昨天下午';
                        $endWord    = '删除';
                        $start      = mb_strpos($word, $beginWord) + mb_strlen($beginWord);
                        $end        = $issetDel ? (mb_strpos($word, $endWord) - $start) : mb_strlen($word) - 1;
                        $string     = mb_substr($word, $start, $end);
                        $screenDate = date('Y-m-d ' . str_replace('：', ':', $string), strtotime('-1 day'));//截图时间
                        if (mb_substr($string, 0, mb_strpos($string, '：')) < 12) {
                            $screenDate = date('Y-m-d H:i', strtotime($screenDate) + $hours);
                        }
                    } else {
                        $beginWord  = '昨天';
                        $endWord    = '删除';
                        $start      = mb_strpos($word, $beginWord) + mb_strlen($beginWord);
                        $end        = $issetDel ? (mb_strpos($word, $endWord) - $start) : mb_strlen($word) - 1;
                        $string     = mb_substr($word, $start, $end);
                        $screenDate = date('Y-m-d ' . str_replace('：', ':', $string), strtotime('-1 day'));//截图时间
                    }
                } elseif (mb_strpos($word, '：') !== false && mb_strpos($word, '年') === false) {
                    $word_str = str_replace('：', 0, $word);
                    if (strlen($word_str) < 5 || !is_numeric($word_str)) {
                        continue;
                    }
                }
                //上传时间是否已超过12小时
                if (empty($screenDate) || (!empty($screenDate) && strtotime($screenDate) + $hours < $uploadTime)) {
                    $shareDate = true;
                }
            }
        }


        //作弊码识别失败
        if (!$shareIden) {
            $errCode[] = -1;
        } else {
            //海报生成和上传非同一用户
            if (!$isSameUser) {
                $errCode[] = -6;
            }

            //海报生成和上传非同一活动
            if (!$isSameActivity) {
                $errCode[] = -7;
            }
        }

        //朋友圈保留时长不足12小时，请重新上传
        if (!$shareDate || !$issetDate) {
            $errCode[] = -2;
        }

        //分享分组可见
        if (!$shareDisplay) {
            $errCode[] = -3;
        }

        //请发布到朋友圈并截取朋友圈照片
        if (!$shareType || !$gobalIssetDel || !$shareOwner) {
            $errCode[] = -4;
        }

        //上传截图出错
        if (!$leafKeyWord) {
            $errCode[] = -5;
        }

        //分享无分享语
        if (!$shareKeyword) {
            $errCode[] = -9;
        }

        if (empty($errCode)) {
            $status = 2;
        }

        return [$status, $errCode];
    }

    public static function getOcrContent($imagePath)
    {
        //调用ocr-识别图片
        $host    = "https://tysbgpu.market.alicloudapi.com";
        $path    = "/api/predict/ocr_general";
        $appcode = "af272f9db1a14eecb3d5c0fb1153051e";
        //根据API的要求，定义相对应的Content-Type
        $headers = [
            'Authorization' => 'APPCODE ' . $appcode,
            'Content-Type'  => 'application/json; charset=UTF-8'
        ];
        $bodys   = [
            'image'     => $imagePath,
            'configure' => [
                'min_size'                     => 1, #图片中文字的最小高度，单位像素
                'output_prob'                  => true,#是否输出文字框的概率
                'output_keypoints'             => false, #是否输出文字框角点
                'skip_detection'               => false,#是否跳过文字检测步骤直接进行文字识别
                'without_predicting_direction' => true#是否关闭文字行方向预测
            ]
        ];
        $url     = $host . $path;
        return HttpHelper::requestJson($url, $bodys, 'POST', $headers);
    }
}
