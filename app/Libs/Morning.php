<?php
/**
 * 清晨项目
 */

namespace App\Libs;

use App\Libs\Exceptions\RunTimeException;

class Morning
{
    const STUDENT_PROFILE_LIST   = '/api/student/profile/list'; // 获取学生列表
    const STUDENT_UUID           = '/api/student/uuid'; // 获取学生的uuid
    const WECHAT_ACCESS_ATOKEN   = '/api/common/wechat/access_token'; // 获取清晨公众号的access_token
    const MINI_APP_ACCESS_ATOKEN = '/api/common/mini_app/access_token'; // 获取清晨小程序access token

    private $host;

    public function __construct()
    {
        $this->host = DictConstants::get(DictConstants::SERVICE, "morning_host");
    }

    private function commonAPI($api, $data = [], $method = 'GET')
    {
        try {
            $fullUrl = $this->host . $api;
            return HttpHelper::requestJson($fullUrl, $data, $method);
        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [$e->getMessage()]);
        }
        return false;
    }


    /**
     * 获取学生列表 - 最多1000个
     * @param $studentUuids
     * @return array
     */
    public function getStudentList($studentUuids)
    {
        SimpleLogger::info('getStudentList params', [$studentUuids]);
        if (empty($studentUuids)) {
            return [];
        }
        $params = [
            'uuid' => $studentUuids,
        ];
        $res = self::commonAPI(self::STUDENT_PROFILE_LIST, $params, 'POST');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('getStudentList_error', [$res, $params]);
            return [];
        }
        SimpleLogger::info('getStudentList', [$res]);
        $studentList = !empty($res['data']) ? $res['data'] : [];
        return is_array($studentList) ? $studentList : [];
    }

    /**
     * 根据open_id获取uuid
     * @param $openIds
     * @return array
     */
    public function getStudentUuidByOpenId($openIds)
    {
        SimpleLogger::info('getStudentUuidByOpenId params', [$openIds]);
        if (empty($studentUuids)) {
            return [];
        }
        $params = [
            'open_id' => $openIds,
        ];
        $res = self::commonAPI(self::STUDENT_UUID, $params, 'POST');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('getStudentUuidByOpenId_error', [$res, $params]);
            return [];
        }
        SimpleLogger::info('getStudentUuidByOpenId', [$res]);
        $studentList = !empty($res['data']) ? $res['data'] : [];
        return is_array($studentList) ? $studentList : [];
    }

    /**
     * 获取公众号的access_token
     * @return array
     */
    public function getWeChatAccessToken()
    {
        SimpleLogger::info('getWeChatAccessToken params', []);
        $params = [
            'source' => Constants::SELF_APP_ID,
        ];
        $res = self::commonAPI(self::WECHAT_ACCESS_ATOKEN, $params, 'GET');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('getWeChatAccessToken_error', [$res, $params]);
            return [];
        }
        SimpleLogger::info('getWeChatAccessToken', [$res]);
        $studentList = !empty($res['data']) ? $res['data'] : [];
        return is_array($studentList) ? $studentList : [];
    }

    /**
     * 获取小程序的access_token
     * @return array
     */
    public function getMiniAppAccessToken()
    {
        SimpleLogger::info('getMiniAppAccessToken params', []);
        $params = [
            'source' => Constants::SELF_APP_ID,
        ];
        $res = self::commonAPI(self::MINI_APP_ACCESS_ATOKEN, $params, 'GET');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('getMiniAppAccessToken_error', [$res, $params]);
            return [];
        }
        SimpleLogger::info('getMiniAppAccessToken', [$res]);
        $studentList = !empty($res['data']) ? $res['data'] : [];
        return is_array($studentList) ? $studentList : [];
    }
}
