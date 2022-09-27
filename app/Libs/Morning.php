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
    // 根据uuid获取openid http://yapi.xiaoyezi.com/project/786/interface/api/25150
    const STUDENT_OPENID = '/api/op/student/openid';
    // 根据token获取uuid http://yapi.xiaoyezi.com/project/786/interface/api/25157
    const TOKEN_TO_STUDENT_UUID = '/api/op/student/uuid';
    // 根据uuid获取用户体验课程进度信息 http://yapi.xiaoyezi.com/project/786/interface/api/25171
    const STUDENT_LESSON_SCHEDULE = '/api/op/student/lesson/schedule';
    // 根据uuid获取用户头像、昵称信息 http://yapi.xiaoyezi.com/project/786/interface/api/25164
    const STUDENT_INFO = '/api/op/student/info';

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
        if (empty($openIds)) {
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

    /**
     * 根据学生uuid获取清晨公众号对应的openid
     * @param array $uuids
     * @return array
     * @throws RunTimeException
     */
    public function getStudentOpenidByUuid(array $uuids)
    {
        SimpleLogger::info('getStudentOpenidByUuid params', $uuids);
        $returnData = [];
        $chunkList = array_chunk(array_unique($uuids), 200);
        foreach ($chunkList as $_uuids) {
            $params = ['uuid' => $_uuids];
            $res = self::commonAPI(self::STUDENT_OPENID, $params, 'POST');
            if ($res['code'] != Valid::CODE_SUCCESS) {
                SimpleLogger::error('getStudentOpenidByUuid_error', [$res, $params]);
                throw new RunTimeException(['erp_system_busy']);
            }
            SimpleLogger::info('getStudentOpenidByUuid', [$res]);
            $_data = !empty($res['data']) ? $res['data'] : [];
            $returnData +=$_data;
        }
        return $returnData;
    }

    /**
     * 获取清晨token对应的用户uuid
     * @param $token
     * @return array
     */
    public function getTokenUuid($token)
    {
        SimpleLogger::info('getTokenUuid params', [$token]);
        $params = [
            'token' => $token
        ];
        $res = self::commonAPI(self::TOKEN_TO_STUDENT_UUID, $params, 'POST');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('getTokenUuid_error', [$res, $params]);
            return [];
        }
        SimpleLogger::info('getTokenUuid', [$res]);
        $data = !empty($res['data']) ? $res['data'] : [];
        return is_array($data) ? $data : [];
    }


    /**
     * 根据uuid获取用户清晨体验课程进度信息
     * @param array $uuids
     * @return array
     */
    public function getStudentLessonSchedule(array $uuids)
    {
        SimpleLogger::info('getStudentLessonSchedule params', $uuids);
        $params = [
            'uuid' => $uuids
        ];
        $res = self::commonAPI(self::STUDENT_LESSON_SCHEDULE, $params, 'POST');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('getStudentLessonSchedule_error', [$res, $params]);
            return [];
        }
        SimpleLogger::info('getStudentLessonSchedule', [$res]);
        $data = !empty($res['data']) ? $res['data'] : [];
        return is_array($data) ? $data : [];
    }

    /**
     * 根据uuid获取用户清晨头像、昵称信息
     * @param array $uuids
     * @return array
     * @throws RunTimeException
     */
    public function getStudentInfo(array $uuids)
    {
        SimpleLogger::info('getStudentInfo params', $uuids);
        $returnData = [];
        $chunkList = array_chunk(array_unique($uuids), 200);
        foreach ($chunkList as $_uuids) {
            $params = ['uuid' => $_uuids];
            $res = self::commonAPI(self::STUDENT_INFO, $params, 'POST');
            if ($res['code'] != Valid::CODE_SUCCESS) {
                SimpleLogger::error('getStudentInfo_error', [$res, $params]);
                throw new RunTimeException(['erp_system_busy']);
            }
            SimpleLogger::info('getStudentInfo', [$res]);
            $_data = !empty($res['data']) ? $res['data'] : [];
            $returnData += $_data;
        }
        return $returnData;
    }
}
