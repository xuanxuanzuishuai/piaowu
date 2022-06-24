<?php
/**
 * 清晨项目
 */

namespace App\Libs;

use App\Libs\Exceptions\RunTimeException;

class Morning
{
    const STUDENT_PROFILE_LIST = '/api/student/profile/list'; // 获取学生列表

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
}
