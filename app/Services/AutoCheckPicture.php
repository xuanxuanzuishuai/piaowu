<?php


namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\OperationActivityModel;
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
        $activity = WeekActivityModel::getById($result['activity_id'], ['id', 'enable_status', 'start_time']);
        if (empty($activity) || $activity['enable_status'] != OperationActivityModel::ENABLE_STATUS_ON) {
            SimpleLogger::error('not found activity', ['id' => $result['activity_id']]);
            return null;
        }
        $start_time = date('m-d', $activity['start_time']);
        $start_time = str_replace('-', '.', $start_time);
        $date = '';
        foreach (str_split($start_time) as $key => $val) {
            if ($key != strlen($start_time) - 1 && $val == '0') {
                continue;
            }
            $date .= $val;
        }
        $redis = RedisDB::getConn();
        $cacheKey = 'letterIden';
        if (!$redis->hexists($cacheKey, $date)) {
            $letterIden = self::transformDate($activity['start_time']);
            $redis->hset($cacheKey, $date, $letterIden);
            $redis->expire($cacheKey, self::$redisExpire);
        }
        $letterIden = $redis->hget($cacheKey, $date);
        return [$date, $letterIden, AliOSS::replaceCdnDomainForDss($result['image_path'])];
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
                $result = SharePosterModel::getRecord(['id' => $data['id'],'type' => SharePosterModel::TYPE_WEEK_UPLOAD,'verify_status' => SharePosterModel::VERIFY_STATUS_WAIT],['student_id','activity_id','image_path']);
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
        $historyRecord = SharePosterModel::getRecord($conds, ['id']);
        return compact('result', 'historyRecord');
    }

    /**
     * 日期转换为对应标识
     * @param $date
     * @return string
     */
    public static function transformDate($date)
    {
        $date       = explode('-', date('Y-m-d', $date));
        $array      = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
        $letterIden = '';
        foreach ($date as $key => $val) {
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
                    if ($val < 8) {
                        $day = 0;
                    } elseif ($val < 15) {
                        $day = 1;
                    } elseif ($val < 22) {
                        $day = 2;
                    } else {
                        $day = 3;
                    }
                    $letterIden .= $array[$day] ?? 'A';
                    break;
            }
        }
        return $letterIden;
    }

    /**
     * 审核结果
     * @param $data
     * @param $status
     * @throws RunTimeException
     */
    public static function checkSharePosters($data, $status)
    {
        if (!$status) {
            return;
        }
        $poster_id  = $data['id'];
        $params['employee_id']  = EmployeeModel::SYSTEM_EMPLOYEE_ID;
        if ($status > 0) {
            //审核通过
            SharePosterService::approvalPoster([$poster_id], $params);
        } else {
            switch ($status) {
                case SharePosterModel::SYSTEM_REFUSE_CODE_NEW: //未使用最新海报
                    $params['reason'] = [SharePosterModel::SYSTEM_REFUSE_REASON_CODE_NEW];
                    break;
                case SharePosterModel::SYSTEM_REFUSE_CODE_TIME: //朋友圈保留时长不足12小时，请重新上传
                    $params['reason'] = [SharePosterModel::SYSTEM_REFUSE_REASON_CODE_TIME];
                    break;
                case SharePosterModel::SYSTEM_REFUSE_CODE_GROUP: //分享分组可见
                    $params['reason'] = [SharePosterModel::SYSTEM_REFUSE_REASON_CODE_GROUP];
                    break;
                case SharePosterModel::SYSTEM_REFUSE_CODE_FRIEND: //请发布到朋友圈并截取朋友圈照片
                    $params['reason'] = [SharePosterModel::SYSTEM_REFUSE_REASON_CODE_FRIEND];
                    break;
                case SharePosterModel::SYSTEM_REFUSE_CODE_UPLOAD: //上传截图出错
                    $params['reason'] = [SharePosterModel::SYSTEM_REFUSE_REASON_CODE_UPLOAD];
                    break;
                default:
                    break;
            }
            //审核拒绝
            SharePosterService::refusedPoster($poster_id, $params);
        }
    }

    /**
     * ocr审核海报
     * @param $data [图片|需要校验的角标日期]
     * @return int|bool
     */
    public static function checkByOcr($data)
    {
        list($checkDate,$letterIden,$image) = $data;

        //调用ocr-识别图片
        $host = "https://tysbgpu.market.alicloudapi.com";
        $path = "/api/predict/ocr_general";
        $appcode = "af272f9db1a14eecb3d5c0fb1153051e";
        //根据API的要求，定义相对应的Content-Type
        $headers = [
            'Authorization' => 'APPCODE '. $appcode,
            'Content-Type' => 'application/json; charset=UTF-8'
        ];
        $bodys = [
            'image' => $image,
            'configure' => [
                'min_size' => 1, #图片中文字的最小高度，单位像素
                'output_prob' => true,#是否输出文字框的概率
                'output_keypoints' => false, #是否输出文字框角点
                'skip_detection' => false,#是否跳过文字检测步骤直接进行文字识别
                'without_predicting_direction' => true#是否关闭文字行方向预测
            ]
        ];
        $url = $host . $path;
        $response = HttpHelper::requestJson($url, $bodys, 'POST', $headers);
        if (!$response) {
            return false;
        }
        $result = array();
        //过滤掉识别率低的
        foreach ($response['ret'] as $val) {
//            if ($val['prob'] < 0.95) {
//                continue;
//            }
            array_push($result, $val);
        }
        $hours           = 3600 * 12; //12小时
        $screenDate     = null; //截图时间初始化
        $uploadTime     = time(); //上传时间
        $contentKeyword = ['小叶子', '琴', '练琴', '很棒', '求赞']; //内容关键字
        $dateKeyword    = ['年', '月', '日', '昨天', '天前', '小时前', '分钟前','上午', '：']; //日期关键字

        $shareType    = false; //分享-类型为朋友圈
        $shareKeyword = false; //分享-关键字存在
        $shareOwner   = false; //分享-自己朋友圈
        $shareCorner  = false; //分享-角标
        $shareDate    = false; //分享-日期超过12小时
        $shareDisplay = true;  //分享-是否显示
        $shareIden    = false; //分享-海报底部字母标识
        $leafKeyWord  = false; //分享-小叶子关键字
        $gobalIssetDel = false; //分享-全局存在删除
        $issetDate     = false; //分享-全局存在时间

        $issetCorner = false;  //分享-角标是否存在
        $status = 0; //-1|-2.审核不通过 0.过滤 2.审核通过
        $patten = "/^(([1-9]|(10|11|12))\.([1-2][0-9]|3[0-1]|[0-9]))$/"; //角标规则匹配
        foreach ($result as $key => $val) {
            $issetDel = false; //是否包含有删除
            $word      = $val['word'];
            //判断1.详情朋友圈
            if (!$shareType && ($word == '朋友圈' || $word == '详情') && $val['rect']['top'] < 200) {
                $shareType = true;
                continue;
            }
            //判断2.角标
            //特殊处理 部分图片日期如5.10 会识别为(5.10
            if (strstr($word, '(')) {
                $word = str_replace('(', '', $word);
            }
            //识别到角标且在删除之前的
            if (preg_match($patten, $word) && !$shareOwner && $shareType) {
                $issetCorner = true;
                if ($word === $checkDate) {
                    $shareCorner = true;
                } else {
                    $status = -1;
                }
            }
            //小叶子关键字
            if (mb_strpos($word, '小叶子') !== false) {
                $leafKeyWord = true;
            }
            //右下角标识
            if (mb_strpos($word, ' ') !== false) {
                $word = str_replace(' ', '', $word);
            }
            if ($word == $letterIden) {
                $shareIden = true;
            }
            //判断3.关键字
            if (!$issetCorner && $shareType && (mb_strlen($word) > 5 || Util::sensitiveWordFilter($contentKeyword, $word) == true)) {
                $shareKeyword = true;
            }
            if (mb_strpos($word, '删除') !== false) {
                $issetDel = true;
                $gobalIssetDel = true;
            }
            //判定是否是自己朋友圈-是否有删除文案且距离顶部的高度大于海报高度(580)
            if ($issetDel && $val['rect']['top'] > 300) {
                $shareOwner = true;
            }
            //屏蔽类型-设置私密照片
            if ($shareOwner && mb_strpos($word, '私密照片') !== false) {
                $status = -3;
                break;
            }
            //上传时间处理 根据坐标定位
            if (($shareIden || $shareCorner) && !$issetDate && Util::sensitiveWordFilter($dateKeyword, $word) == true) {
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

                //特殊情况-第一张图 发布时间和删除下标相同
                if ($shareOwner && !$issetDel) {
                    continue;
                }
                if (mb_strpos($word, '分钟前') !== false) {
                    $status = -2;
                    break;
                }
                if (mb_strpos($word, '：') !== false && mb_strlen($word) == 5) {
                    $word_str = str_replace('：', 0, $word);
                    if (!is_numeric($word_str)) {
                        continue;
                    }
                    $screenDate = date('Y-m-d ' . str_replace('：', ':', $word));//截图时间
                } elseif (mb_strpos($word, '小时前') !== false) {
                    $endWord    = '小时前';
                    $start       = 0;
                    $end         = mb_strpos($word, $endWord) - $start;
                    $string      = mb_substr($word, $start, $end);
                    $screenDate = date('Y-m-d H:i', strtotime('-' . $string . ' hours'));
                } elseif (Util::sensitiveWordFilter(['昨天', '年'], $word) === false && mb_strpos($word, '上午') !== false) { //当做今天的 下午可忽略
                    $beginWord  = '上午';
                    $endWord    = '删除';
                    $start       = mb_strpos($word, $beginWord) + mb_strlen($beginWord);
                    $end         = $issetDel ? (mb_strpos($word, $endWord) - $start) : mb_strlen($word) - 1;
                    $string      = mb_substr($word, $start, $end);
                    $screenDate = date('Y-m-d ' . str_replace('：', ':', $string));//截图时间
                } elseif (mb_strpos($word, '昨天') !== false) {
                    if (mb_strlen($word) == 2) {
                        $screenDate = date('Y-m-d', strtotime('-1 day'));
                    } elseif (mb_strpos($word, '上午') !== false || mb_strpos($word, '凌晨') !== false) {
                        $beginWord = '昨天上午';
                        $endWord   = '删除';
                        $start       = mb_strpos($word, $beginWord) + mb_strlen($beginWord);
                        $end         = $issetDel ? (mb_strpos($word, $endWord) - $start) : mb_strlen($word) - 1;
                        $string      = mb_substr($word, $start, $end);
                        $screenDate = date('Y-m-d ' . str_replace('：', ':', $string), strtotime('-1 day'));//截图时间
                    } elseif (mb_strpos($word, '下午') !== false) {
                        $beginWord = '昨天下午';
                        $endWord   = '删除';
                        $start       = mb_strpos($word, $beginWord) + mb_strlen($beginWord);
                        $end         = $issetDel ? (mb_strpos($word, $endWord) - $start) : mb_strlen($word) - 1;
                        $string      = mb_substr($word, $start, $end);
                        $screenDate = date('Y-m-d ' . str_replace('：', ':', $string), strtotime('-1 day'));//截图时间
                        $screenDate = date('Y-m-d H:i', strtotime($screenDate) + $hours);
                    } else {
                        $beginWord  = '昨天';
                        $endWord    = '删除';
                        $start       = mb_strpos($word, $beginWord) + mb_strlen($beginWord);
                        $end         = $issetDel ? (mb_strpos($word, $endWord) - $start) : mb_strlen($word) - 1;
                        $string      = mb_substr($word, $start, $end);
                        $screenDate = date('Y-m-d ' . str_replace('：', ':', $string), strtotime('-1 day'));//截图时间
                    }
                } elseif (mb_strpos($word, '：') !== false && mb_strpos($word, '年') === false) {
                    $word_str = str_replace('：', 0, $word);
                    if (strlen($word_str) < 5 || !is_numeric($word_str)) {
                        continue;
                    }
                }
                $issetDate = true;
                //上传时间是否已超过12小时
                if (empty($screenDate) || (!empty($screenDate) && strtotime($screenDate) + $hours < $uploadTime)) {
                    $shareDate = true;
                } else {
                    if ($status == -1 && !$shareIden) {
                        $status = -1;
                        break;
                    }
                    $status = -2;
                    break;
                }
                //判定是否被屏蔽 特殊情况:发布时间和删除下标相同
                if (!$shareOwner && isset($result[$key + 1]) && Util::sensitiveWordFilter(['删除','智能陪练','：','册','删'], $result[$key + 1]['word']) == false) {
                    $shareDisplay = false;
                }
            }
        }
        //角标识别错误 && 字符串识别正确则往下判断
        if ($status == -1 && $shareIden) {
            $status = 0;
        }
        if ($status < 0) {
            return $status;
        }
        //包含朋友圈或详情 且没有删除
        if ($shareType && !$gobalIssetDel) {
            return -4;
        }
        //未识别到角标&&未识别到右下角标识&&未识别到小叶子
        if (!$issetCorner && !$shareIden && !$leafKeyWord) {
            return -5;
        }
        if ($shareType && $shareKeyword && $shareOwner && $shareDate && $shareDisplay && ($shareCorner || $shareIden )) {
            $status = 2;
        }
        return $status;
    }
}