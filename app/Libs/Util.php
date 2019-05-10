<?php

namespace App\Libs;

use App\Models\TeacherModel;
use App\Services\DictService;

/**
 * 工具类
 * @author tianye@xiaoyezi.com
 * @since 2017-04-05 15:05:53
 */
class Util
{
    const TIMESTAMP_5M = 300;
    const TIMESTAMP_1H = 3600;
    const TIMESTAMP_2H = 7200;
    const TIMESTAMP_3H = 10800;
    const TIMESTAMP_6H = 21600;
    // 一天
    const TIMESTAMP_ONEDAY = 86400;
    // 一周
    const TIMESTAMP_ONEWEEK = 604800;

    /**
     * 生成Token
     */
    public static function token()
    {
        $token = md5(bin2hex(uniqid(rand(), true)));
        return $token;
    }

    public static function randString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * 验证身份证号
     * @param $vStr
     * @return bool|array
     */
    public static function idNumberCheck($vStr)
    {
        $vCity = array(
            '11', '12', '13', '14', '15', '21', '22',
            '23', '31', '32', '33', '34', '35', '36',
            '37', '41', '42', '43', '44', '45', '46',
            '50', '51', '52', '53', '54', '61', '62',
            '63', '64', '65', '71', '81', '82', '91'
        );

        if (!preg_match('/^([\d]{17}[xX\d]|[\d]{15})$/', $vStr))
            return false;

        if (!in_array(substr($vStr, 0, 2), $vCity))
            return false;

        $vStr = preg_replace('/[xX]$/i', 'a', $vStr);
        $vLength = strlen($vStr);

        if ($vLength == 18) {
            $vBirthday = substr($vStr, 6, 4) . '-' . substr($vStr, 10, 2) . '-' . substr($vStr, 12, 2);

        } else {
            $vBirthday = '19' . substr($vStr, 6, 2) . '-' . substr($vStr, 8, 2) . '-' . substr($vStr, 10, 2);
        }

        if (date('Y-m-d', strtotime($vBirthday)) != $vBirthday)
            return false;

        if ($vLength == 18) {
            $vSum = 0;

            for ($i = 17; $i >= 0; $i--) {
                $vSubStr = substr($vStr, 17 - $i, 1);
                $vSum += (pow(2, $i) % 11) * (($vSubStr == 'a') ? 10 : intval($vSubStr, 11));
            }

            if ($vSum % 11 != 1)
                return false;
        }

        # 1男, 2女
        $gender = 2;
        if (intval(substr($vStr, 16, 1)) % 2 == 1) {
            $gender = 1;
        }

        return [$vBirthday, $gender];
    }

    /**
     * 获取当前毫秒
     */
    public static function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * 获取第二天0点时间戳
     * @return false|int
     */
    public static function getTomorrowTime()
    {
        $date = date("Y-m-d");
        return strtotime($date . " 1 day");
    }

    /**
     * 隐藏手机号中间4位数字
     * @param $mobile
     * @return mixed
     */
    public static function hideUserMobile($mobile)
    {
        if (empty($mobile)) {
            return '';
        }
        return substr_replace(trim($mobile), '****', 3, 4);
    }


    /**
     * @param string $ip
     * @param array $whiteList
     * @return bool
     */
    public static function checkIp($ip, $whiteList)
    {
        foreach ($whiteList as $item) {
            if ($item == $ip) {
                return true;
            } else {
                $itemArr = explode(".", $item);
                $ipArr = explode(".", $ip);
                $flag = true;
                foreach ($ipArr as $k => $v) {
                    if ($itemArr[$k] != "*" && $itemArr[$k] != $ipArr[$k]) {
                        $flag = false;
                        break;
                    }
                }
                if ($flag)
                    return true;
                else continue;
            }
        }
        return false;
    }

    /**
     * 手机号格式检查
     * @param $mobile
     * @return int
     */
    public static function isMobile($mobile)
    {
        return preg_match(Constants::MOBILE_REGEX, $mobile);
    }

    /**
     * 比率
     * @param $a
     * @param $b
     * @return string
     */
    public static function rate($a, $b)
    {
        return $b > 0 ? round($a / $b * 100, 2) . '%' : '/';
    }

    /**
     * SQL模糊搜索添加%
     * @param $arg
     * @return string
     */
    public static function sqlLike($arg)
    {
        if (!is_string($arg) && !is_numeric($arg)) {
            return $arg;
        }
        return "%{$arg}%";
    }

    /**
     * 验证时间格式
     * 1.时间格式必须是日期+时间，时间截止至多少分钟，分钟必须是 整点或半点：
     * 例如：2018-11-04 8:00
     * @param $time
     * @return bool
     */
    public static function checkTime($time){
        $check_time = false;

        //判断开始时间和结束时间格式 包含分钟不包含秒
        $preg = '/^([12]\d\d\d)-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[0-1]) (([0-1])?\d|2[0-4]):([0-5]\d)(:[0-5]\d)?$/';
        $format_1 = preg_match($preg, $time);

        //验证时间必须是整点或者半点
        $format_2 = intval(date('i',strtotime($time)));

        if ($format_1 == 1 && ($format_2 == 0 || $format_2 == 30)){
            $check_time = true;
        }
        return $check_time;
    }

    /**
     * 格式化开始时间
     * @param $startTime
     * @return mixed
     */
    public static function formatStartTime($startTime)
    {
        $remainder = $startTime % 1800;
        if ($remainder > 0) {
            return $startTime - $remainder;
        }
        return intval($startTime);
    }

    /**
     * 格式化结束时间
     * @param $endTime
     * @return mixed
     */
    public static function formatEndTime($endTime)
    {
        $remainder = $endTime % 1800;
        if ($remainder > 0) {
            return $endTime - $remainder + 1800;
        }
        return intval($endTime);
    }

    /**
     * 获取整周时间
     * 参数时间格式 Y-m-d H:i
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getWholeWeek($startTime, $endTime = null){
        if (!isset($endTime)){
            $endTime = $startTime;
        }
        $weekIndex = date('N', $startTime) - 1;
        $endWeekIndex = date('N', $endTime) - 1;
        $startTimeMonday = strtotime(date('Y-m-d', $startTime)) - $weekIndex * self::TIMESTAMP_ONEDAY;
        $endTimeSundayEnd = strtotime(date('Y-m-d', $endTime)) - $endWeekIndex * self::TIMESTAMP_ONEDAY + self::TIMESTAMP_ONEWEEK;
        return [$startTimeMonday, $endTimeSundayEnd];
    }
    /**
     * 时间转化字符串
     * @param $date
     * @return false|string
     */
    public static function formatTimestamp($date)
    {
        if (!empty($date)) {
            return date('Y-m-d H:i:s', $date);
        }
        return '-';
    }

    /**
     * 验证日期格式，年-月-日(2018-11-10)
     * @param $date
     * @return bool
     */
    public static function checkDate($date){
        $check_time = false;

        //判断开始时间和结束时间格式 包含分钟不包含秒
        $preg = '/^([12]\d\d\d)-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[0-1])$/';
        $format = preg_match($preg, $date);

        if ($format == 1){
            $check_time = true;
        }
        return $check_time;
    }
    /**
     * 下划线转驼峰
     */
    public static function underlineToHump($str)
    {
        $str = preg_replace_callback('/([-_]+([a-z]{1}))/i',function($matches){
            return strtoupper($matches[2]);
        },$str);
        return $str;
    }

    /**
     * 格式化分页参数
     * @param $params
     * @return array
     */
    public static function formatPageCount($params) {
        //格式化page 页数
        if(empty($params['page']) || !is_numeric($params['page']) || (int)$params['page']<1) {
            $page = 1;
        }else{
            $page = (int)$params['page'];
        }
        //格式化count 分页数量
        if(empty($params['count']) || !is_numeric($params['count']) || (int)$params['count']<1) {
            $defaultCount = DictService::getKeyValue(Constants::DICT_TYPE_SYSTEM_ENV,Constants::DEFAULT_PAGE_LIMIT);
            $count = $defaultCount ?: 20;
        }else{
            $count = (int)$params['count'];
        }
        return [$page, $count];
    }

    /**
     * @param $time
     * @return mixed
     */
    public static function getShortWeekName($time){
        $week = array("周一","周二","周三","周四","周五","周六","周日");
        return $week[date('N', $time) - 1];
    }

    /**
     * 根据日期获取星期几
     * @param $time @时间戳
     * @return mixed
     */
    public static function getWeekName($time)
    {
        $week_array=array("星期一","星期二","星期三","星期四","星期五","星期六","星期日");
        $week_name = $week_array[date("N",$time)-1];
        return $week_name;
    }

    /**
     * 判断参数是否为整数
     * @param $num
     * @return bool
     */
    public static function isInt($num)
    {
        if(is_numeric($num) && strpos($num, '.') === false){
            return true;
        }
        return false;
    }

    /**
     * [1,2,3] => '1','2','3'
     * 将数组转化为sql可以识别的语句
     * @param $array
     * @return bool|string
     */
    public static function buildSqlIn($array) {
        if( ! is_array($array)) {
            return '';
        }
        if(count($array) == 0){
            return '';
        }
        $s = '';
        foreach($array as $a) {
            $s .= "'{$a}',";
        }

        return substr($s,0,-1);
    }

    /**
     * 计算退费金额并返回计算公式，传入金额的参数和返回的参数，单位都是元
     * @param $averageConsumePrice
     * @param $lastTimeConsumePrice
     * @param $formalCourseRefundCount
     * @param $freeCourseRefundCount
     * @return array
     */
    public static function computeRefund($averageConsumePrice,$lastTimeConsumePrice,$formalCourseRefundCount,$freeCourseRefundCount) {
        $a = $averageConsumePrice / 100;
        $l = $lastTimeConsumePrice / 100;

        if($formalCourseRefundCount < 1){
            return [0,"{$a} * 0 + {$l} * 0 + 0 * {$freeCourseRefundCount}"];
        }else if($formalCourseRefundCount == 1) {
            return [$lastTimeConsumePrice,"{$l} * 0 + 0 * {$freeCourseRefundCount}"];
        }else{
            $rest = $formalCourseRefundCount - 1;
            return [
                $averageConsumePrice * ($formalCourseRefundCount - 1) + $lastTimeConsumePrice * 1,
                "{$a} * {$rest} + {$l} * 1 + 0 * {$freeCourseRefundCount}"
            ];
        }
    }

    /**
     * 获取七牛图片完整链接地址
     * @param $img
     * @return string
     */
    public static function getQiNiuFullImgUrl($img)
    {
        if (empty($img)) {
            return '';
        }
        if (preg_match("/^(http:\/\/|https:\/\/).*$/", $img)) {
            return $img;
        }
        list($domain, $qiniuFolder) = DictService::getKeyValuesByArray(
            Constants::DICT_TYPE_SYSTEM_ENV,
            [
                'QINIU_DOMAIN_10',
                'QINIU_FOLDER_10'
            ]);
        $img_url = 'https://' . $domain . '/' . $qiniuFolder . "/" . $img;
        return $img_url;
    }

    /**
     * 校验日期格式是否合法
     * @param string $date
     * @param array $formats
     * @return bool
     */
    public static function isDateValid($date, $formats = array('Y-m-d', 'Y/m/d', 'Y/n/j')) {
        $unixTime = strtotime($date);
        if(!$unixTime) { //无法用strtotime转换，说明日期格式非法
            return false;
        }
        //校验日期合法性，只要满足其中一个格式就可以
        foreach ($formats as $format) {
            if(date($format, $unixTime) == $date) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取当前毫秒时间戳
     * @return float
     */
    public static function milliSecond() {
        list($m, $sec) = explode(' ', microtime());
        return intval(sprintf('%.0f', (floatval($m) + floatval($sec)) * 1000));
    }

    /**
     * 获取ip
     * @return mixed
     */
    public static function IP(){
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * 转化出生日期
     * @param $birthday
     * @return mixed
     */
    public static function formatBirthday($birthday){
        if(self::isInt($birthday)  && in_array(substr($birthday, 0, 2), [19, 20])){
            if(strlen($birthday) == 4){
                return $birthday.'0101';
            }elseif(strlen($birthday) == 8){
                return $birthday;
            }
        }
        return null;
    }

    /**
     * 格式化年份
     * @param $year
     * @return null|string
     */
    public static function formatYear($year)
    {
        if(self::isInt($year)  && in_array(substr($year, 0, 2), [19, 20])){
            if(strlen($year) == 4){
                return $year;
            }
        }
        return null;
    }

    /**
     * 参数范围检查函数
     * -- 结束值不能为0，切开始值不能大于结束值
     * @param $start
     * @param $end
     * @param $error
     * @return array
     */
    public static function checkRange($start, $end, $error)
    {
        if ($end === 0 || $end === '0' || (!empty($start) && !empty($end) && $start > $end)) {
            return Valid::addErrors([], $error, $error);
        }
        return ['code' => Valid::CODE_SUCCESS];
    }

    /**
     * 检查开始时间是否在节假日
     * @param $sTime
     * @return bool
     */
    public static function holidayTimeCheck($sTime)
    {
        if ($sTime > 1549123200 && $sTime < 1549814400) {
            return false;
        }
        return true;
    }

    /**
     * 组装limit语句
     * @param $page
     * @param $count
     * @return string
     */
    public static function limitation($page, $count)
    {
        list($page, $count) = self::formatPageCount(['page' => $page,'count' => $count]);
        $limit = ($page - 1) * $count;
        return " limit {$limit},{$count} ";
    }

    /**
     * app端分页
     * @param $params
     * @return array
     */
    public static function appPageLimit($params)
    {
        //格式化page 页数
        if (empty($params['page_id']) || !is_numeric($params['page_id']) || (int)$params['page_id'] < 1) {
            $page = 1;
        } else {
            $page = (int)$params['page_id'];
        }
        //格式化count 分页数量
        if (empty($params['page_limit']) || !is_numeric($params['page_limit']) || (int)$params['page_limit'] < 1) {
            $count = 100;
        } else {
            $count = (int)$params['page_limit'];
        }
        return [$page, $count];
    }

    public static function unusedParam($param)
    {
        if (empty($param)) { NULL; /* unused params */ }
    }

    /**
     * 学生注册时的默认昵称
     * @param $mobile
     * @return string
     */
    public static function defaultStudentName($mobile)
    {
        return '宝贝' . substr($mobile, -4, 4);
    }

    /**
     * 判断是否网址
     * @param $url
     * @return int
     */
    public static function isUrl($url){
        return preg_match("/^http[s]?:\/\/.+$/", $url);
    }

    public static function makeOrgLoginName($orgId, $loginName)
    {
        return "org{$orgId}_$loginName";
    }

    /**
     * 判断一个浮点数是否是整数 e.g floatIsInt(1.0) => true; floatIsInt(1.1) => false;
     * @param $f
     * @return bool
     */
    public static function floatIsInt($f){
        if(!is_numeric($f)){
            return false;
        }
        return  (int)$f == $f ? true : false;
    }

    /**
     * 判断一个浮点数是否是整数，如果是转换为整数
     * @param $f
     * @return int
     */
    public static function convertToIntIfCan($f){
        if(self::floatIsInt($f)){
            return (int)$f;
        }
        return $f;
    }

    /**
     * 格式化练琴时间，将秒转为x分x秒
     * @param $seconds
     * @return string
     */
    public static function formatExerciseTime($seconds)
    {
        $minute = floor($seconds / 60);
        $second = $seconds % 60;

        $str = '';
        if($minute > 0) {
            $str .= $minute . '分钟';
        }
        if($second > 0) {
            $str .= $second . '秒';
        }
        if(empty($str)) {
            $str = '0秒';
        }

        return $str;
    }
}
