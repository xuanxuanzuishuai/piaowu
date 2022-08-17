<?php
/**
 * 羊毛系统
 */

namespace App\Libs;

use App\Libs\Exceptions\RunTimeException;
use Exception;

class Risk
{
    const WOOL_CHECK = '/api/v1/wool/check'; // 预查询羊毛信息【业务使用】

    private $host;

    public function __construct()
    {
        $this->host = $_ENV['RISK_HOST'];
    }

    private function commonAPI($api, $data = [], $method = 'GET')
    {
        try {
            $fullUrl = $this->host . $api;
            return HttpHelper::requestJson($fullUrl, $data, $method);
        } catch (Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [$e->getMessage()]);
        }
        return false;
    }

    /**
     * 预查询羊毛信息【业务使用】
     * @param $params
     * @return array|mixed
     */
    public function getStudentIsRepeat($params)
    {
        $params = [
            'source_app_id' => $params['source_app_id'] ?? Constants::SMART_APP_ID,
            'uuid'          => $params['uuid'] ?? '',
            'open_id'       => $params['open_id'] ?? '',
        ];
        $res = self::commonAPI(self::WOOL_CHECK, $params);
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('getStudentIsRepeat_error', [$res, $params]);
            return [];
        }
        SimpleLogger::info('getStudentIsRepeat', [$params, $res]);
        return !empty($res['data']) ? $res['data'] : [];
    }
}
