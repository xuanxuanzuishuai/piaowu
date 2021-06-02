<?php

namespace App\Libs;

use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssGiftCodeModel;
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
    // 两周
    const TIMESTAMP_TWOWEEK = 1209600;
    // 30天
    const TIMESTAMP_THIRTY_DAYS = 2592000;

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

    public static function isChineseMobile($mobile)
    {
        return preg_match('/^1\d{10}$/', $mobile);
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

    /**
     * @param $text
     * @return string|string[]|null
     * 过滤掉emoji表情
     */
    public static function filterEmoji($text)
    {
        $clean_text = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $text);
        return $clean_text;
    }

    /**
     * @param $input
     * @param false $throwError
     * @return false
     * @throws RunTimeException
     */
    public static function containEmoji($input, $throwError = false)
    {
        if (empty($input)) {
            return false;
        }
        $contain = false;
        $contain = preg_replace_callback(
            '/./u',
            function (array $match) use (&$contain) {
                if (strlen($match[0]) >=4 && !$contain) {
                    return true;
                }
            },
            $input
        );
        if ($contain && $throwError) {
            throw new RunTimeException(['emoji_not_allowed']);
        }
        return $contain;
    }

    /**
     * 获取过去n天的起止时间
     * @param int $days. 天数
     * @return array
     */
    public static function nDaysBeforeNow($now=null, $days=7)
    {
        if(empty($now)){
            $now = time();
        }
        $nDaysBefore = (int)$days * 24 * 60 * 60;
        return [$now - $nDaysBefore, $now];
    }

    public static function computeExpire($baseTime, $duration, $durationUnit)
    {
        $unit = 0;
        switch ($durationUnit) {
            case 'year':
                $unit = 365 * 86400;
                break;
            case 'month':
                $unit = 30 * 86400;
                break;
            case 'day':
                $unit = 86400;
                break;
        }
        return $baseTime + $unit * $duration;
    }

    /**
     * 将数组转为bitmap
     * [1, 4, 5] -> 25(11001)
     * @param array $array
     * @return int
     */
    public static function arrayToBitmap($array)
    {
        if (!is_array($array) || empty($array)) {
            return 0;
        }
        $bitmap = 0;
        foreach($array as $bitPos) {
            $pos = (int)$bitPos - 1;
            if ($pos < 0) { return 0; }
            $bitmap |= (1 << $pos);
        };
        return $bitmap;
    }

    /**
     * 将bitmap转为数组
     * 25(11001) -> [5, 4, 1]
     * @param int $bitmap
     * @return array
     */
    public static function bitmapToArray($bitmap)
    {
        if (!is_numeric($bitmap) || empty($bitmap)) {
            return [];
        }
        $array = [];
        $bin = str_split(base_convert($bitmap, 10, 2));
        $size = count($bin);
        foreach ($bin as $i => $bit) {
            if ($bit) {
                $array[] = $size - $i;
            }
        }
        return $array;
    }

    /**
     * 转义特殊符号和emoji表情
     */
    public static function textEncode($str)
    {
        if (!is_string($str)) return $str;
        if (!$str || $str == 'undefined') return '';

        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i", function ($str) {
            return addslashes($str[0]);
        }, $text); //将emoji的unicode留下，其他不动
        return json_decode($text);
    }

    /**
     * 解码转义
     */
    public static function textDecode($str)
    {
        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback('/\\\\\\\\/i', function ($str) {
            return '\\';
        }, $text); //将两条斜杠变成一条，其他不动
        return json_decode($text);
    }

    /**
     * 替换以关键词开头和结尾
     * @param string $string            目标字符串
     * @param string $patternStart      正则匹配规则开始关键字
     * @param string $patternEnd        正则匹配规则结尾关键字
     * @param array  $replaceParams     替换参数数组
     * @return mixed|string|void
     */
    public static function pregReplaceTargetStr($string, $replaceParams, $patternStart = '{{', $patternEnd = '}}')
    {
        $patterns = $replacements = [];
        $resultStr = '';
        //正则匹配数据
        $patternRule = '/(' . $patternStart . '.*?' . $patternEnd . ')/';
        preg_match_all($patternRule, $string, $matches);
        if (empty($matches)) {
            return $resultStr;
        }
        //获取正则替换规则和替换数据
        foreach ($matches[0] as $mv) {
            $findStr = str_replace([$patternStart, $patternEnd], ["/", "/"], $mv);
            $patterns[] = $findStr;
            $replacements[] = $replaceParams[trim($findStr, "/")];
        }
        //替换关键字符
        $string = str_replace([$patternStart, $patternEnd], [], $string);
        $resultStr = preg_replace($patterns, $replacements, $string);
        return $resultStr;
    }

    /**
     * 判断是否为空，0除外，0返回false
     * @param $value
     * @return bool
     */
    public static function emptyExceptZero($value)
    {
        return empty($value) && $value !== 0 && $value !== '0';
    }
    /**
     * 计算两个日期之间相差天数
     * @param string $start    开始日期:2020-03-01
     * @param string $end      结束日期:2020-03-02
     * @return mixed|string|void
     */
    public static function dateDiff($start,$end){
        $datetime_start = date_create($start);
        $datetime_end = date_create($end);
        $days = date_diff($datetime_start, $datetime_end);
        return $days->days;
    }

    /**
     * 获取某天的开始和结束的时间戳
     * @param string $dateTimestamp 日期的时间戳
     * @return array
     */
    public static function getStartEndTimestamp($dateTimestamp)
    {
        $date = date('Ymd', $dateTimestamp);
        $beginDay = strtotime($date);
        $endDay = $beginDay+self::TIMESTAMP_ONEDAY-1;
        return [$beginDay, $endDay];
    }

    /**
     * 计算两个日期之间的天数:包含开始和结束日期
     * @param string $startDate    开始日期:2020-03-01
     * @param string $endDate      结束日期:2020-03-31
     * @return float|int
     */
    public static function dateBetweenDays($startDate, $endDate)
    {
        $startTimeStamp = strtotime($startDate);
        $endTimeStamp = strtotime($endDate) + self::TIMESTAMP_ONEDAY;
        $days = ($endTimeStamp - $startTimeStamp) / self::TIMESTAMP_ONEDAY;
        return $days;
    }

    /**
     * 严重错误，需要发送到sentry
     * @param $message
     * @param array $data
     */
    public static function errorCapture($message, $data = [])
    {
        $sentryClient = new \Raven_Client($_ENV['SENTRY_NOTIFY_URL']);
        $otherInfo = '';
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $str = PHP_EOL . "{$k}: " . $v;
                $otherInfo .= $str;
            }
        }
        $sentryClient->captureMessage('log_uid: ' . SimpleLogger::getWriteUid() . PHP_EOL . 'info: ' . $message . $otherInfo);
    }

    /**
     * 检测字符串的字节长度是否满足要求
     * @param $str
     * @param $checkLength
     * @return bool
     */
    public static function checkStringLength($str, $checkLength)
    {
        $strLength = strlen($str);
        if ($strLength > $checkLength) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取当前月份所属的季度
     * @param $timestamp
     * @return float
     */
    public static function getQuarterByMonth($timestamp)
    {
        $month = date('m', $timestamp);
        $year = date('Y', $timestamp);
        return $year . ceil($month / 3);
    }

    /**
     * 获取目标季度的上&下一个季度
     * @param $quarterNumber 20202
     * @return array
     */
    public static function getUpAndDownQuarter($quarterNumber)
    {
        $year = substr($quarterNumber, 0, 4);
        $quarter = substr($quarterNumber, -1);
        if ($quarter == 1) {
            $upQuarter = 4;
            $upYear = $year - 1;
        } else {
            $upQuarter = $quarter - 1;
            $upYear = $year;
        }
        if ($quarter == 4) {
            $downQuarter = 1;
            $downYear = $year + 1;
        } else {
            $downQuarter = $quarter + 1;
            $downYear = $year;
        }
        return ['up_quarter' => $upYear . $upQuarter, 'down_quarter' => $downYear . $downQuarter];
    }

    /**
     * 通过季度号获取季度的开始结束时间
     * @param $quarterNumber 20202
     * @return array
     */
    public static function getQuarterStartEndTime($quarterNumber)
    {
        $year = substr($quarterNumber, 0, 4);
        $quarter = substr($quarterNumber, -1);
        $startTime = mktime(0, 0, 0, $quarter * 3 - 2, 1, $year);
        $endTime = mktime(23, 59, 59, $quarter * 3, date('t', mktime(0, 0, 0, $quarter * 3, 1, $year)), $year);
        return ['start_time' => $startTime, 'end_time' => $endTime];
    }

    /**
     * 生成全局唯一ID
     * @return string
     */
    public static function makeUniqueId()
    {
        return md5(uniqid(md5(microtime(true)), true));
    }

    /**
     * @param $seconds
     * @return string
     * 将时间段转换为时分秒
     */
    public static function secondToDate($seconds)
    {
        if ($seconds > 3600){
            $hours = intval($seconds/3600);
            $minutes = $seconds % 3600;
            $time = $hours."时".gmstrftime('%M分%S秒', $minutes);
        }else{
            $time = gmstrftime('%M分%S秒', $seconds);
        }
        return $time;
    }

    /**
     * 获取指定日期所在的周的开始结束时间戳
     * @param $timeStamp
     * @return array
     */
    public static function getDateWeekStartEndTime($timeStamp)
    {
        $startTime = strtotime('last sunday next day', $timeStamp);
        $endTime = strtotime('next monday', $timeStamp) - 1;
        return [$startTime, $endTime, date("Y", $timeStamp), date("W", $timeStamp)];
    }

    /**
     * 转换练习时长秒为其他单位
     * @param $duration
     * @param int $unit
     * @return float
     */
    public static function formatDuration($duration, $unit = 60)
    {
        return bcdiv($duration, $unit) + (bcmod($duration, $unit) > 0 ? 1 : 0);
    }

    /**
     * 元 => 分
     * @param $price string 单位元
     * @return int 单位分
     */
    public static function fen($price)
    {
        if (empty($price)) {
            return 0;
        }
        return bcmul($price, 100);
    }

    /**
     * 分 => 元
     * @param $price string 单位分
     * @param int $scale 小数保留几位
     * @return int 单位元
     */
    public static function yuan($price, $scale = 2)
    {
        if (empty($price)) {
            return 0;
        }
        return bcdiv($price, 100, $scale);
    }

    /**
     * 擦除字符串中的所有空白，包括空格、换行和制表符
     * @param $s
     * @return null|string|string[]
     */
    public static function trimAllSpace($s)
    {
        return preg_replace('/[\s\t\n]/', '', $s);
    }

    /**
     * 判断当前浏览器是否是微信内
     * @return bool
     */
    public static function isWx()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return true;
        }
        return false;
    }

    /**
     * 年月日 转换成秒
     * @param $units
     * @param $num
     * @return float|int
     */
    public static function formatDurationSecond($units, $num)
    {
        if ($units == DssGiftCodeModel::CODE_TIME_YEAR) {
            $duration = $num * 366;
        } elseif ($units == DssGiftCodeModel::CODE_TIME_MONTH) {
            $duration = $num * 31;
        } else {
            $duration = $num;
        }
        return $duration;
    }

    /**
     * 敏感词校验
     * @param $keyWordRules
     * @param $content
     * @return bool
     */
    public static function sensitiveWordFilter($keyWordRules,$content){
        if(empty($keyWordRules) || empty($content)) {
            return false;
        }
        $content = str_replace(" ", "", $content);
        foreach ($keyWordRules as $key_word) {
            if (mb_strpos($content, $key_word) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * url后添加参数
     * @param $url
     * @param $params
     */
    public static function urlAddParams(&$url, $params)
    {
        if (empty($url)) {
            return;
        }
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $data);
        foreach ($params as $key => $value) {
            if (isset($data[$key])) {
                unset($params[$key]);
            }
        }
        if (empty($params)) {
            return;
        }
        if (strpos($url, '?')===false) {
            $url .= '?';
        } else {
            $url .= '&';
        }
        $url .= http_build_query($params);
    }
}