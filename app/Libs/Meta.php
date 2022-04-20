<?php
/**
 * meta 日志系统
 * User: qingfeng.lian
 * Date: 2-22/04/13
 * Time: 4:19 PM
 */

namespace App\Libs;

use App\Libs\Exceptions\RunTimeException;
use Exception;

class Meta
{
    const LOG_ADD = '/internal_api/system_log/add'; //刷新

    private $host;

    public function __construct()
    {
        $this->host = DictConstants::get(DictConstants::SERVICE, "meta_host");
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
     * 保存日志
     * @param $data
     * @return array|mixed
     */
    public function saveLog($data)
    {
        $params = [
            'table_name' => $data['table_name'],
            'data_id' => $data['data_id'],
            'old_value' => !empty($data['old_value']) ? json_encode($data['old_value']) : '-',
            'new_value' => json_encode($data['new_value']),
            'batch_id' => !empty($data['batch_id']) ? $data['batch_id'] : uniqid(),
            'menu_name' => $data['menu_name'],
            'event_type' => $data['event_type'],
            'affect_obj_name' => !empty($data['affect_obj_name']) ? $data['affect_obj_name'] : '-',
            'operator_uuid' => $data['operator_uuid'],
            'operator_name' => $data['operator_name'],
            'operator_time' => $data['operator_time'],
            'source_app_id' => $data['source_app_id'],
        ];
        $res    = self::commonAPI(self::LOG_ADD, $params, 'POST');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('meta_save_log_error', [$res, $params]);
            return [];
        }
        SimpleLogger::info('meta_save_log', [$data, $res]);
        return !empty($res['data']) ? $res['data'] : [];
    }
}
