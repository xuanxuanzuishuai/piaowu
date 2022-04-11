<?php
/**
 * 海外投放 - 真人业务线
 * author: qingfeng.lian
 * date: 2022/4/7
 */

namespace App\Services\TraitService;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\AbroadLaunchLeadsInputRecordModel;
use App\Services\RealStudentOverseas\DeliveryService;

trait TraitRealAbroadLaunchService
{

    /**
     * 真人业务线 渠道线索录入 - 不会走登录激活
     * @param $employeeId
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    private function RealChannelSaveLeads($employeeId, $params) {
        $returnData = ['record_id' => 0, 'code' => 0];
        // erp注册账号
        $studentInfo = DeliveryService::do(['params' => $params]);
        SimpleLogger::info('RealChannelSaveLeads', [$employeeId, $params, $studentInfo]);
        // 获取注册结果
        $studentUUID = $studentInfo['uuid'] ?? '';
        if (empty($studentUUID)) {
            throw new RunTimeException(['user_register_fail']);
        }
        // 保存录入记录
        $recordId = AbroadLaunchLeadsInputRecordModel::insertRecord([
            'employee_id' => $employeeId,
            'app_id' => Constants::REAL_APP_ID,
            'country_code' => trim($params['country_code']),
            'mobile' => trim($params['mobile']),
            'user_name' => trim($params['user_name']),
            'wechat' => trim($params['wechat']),
            'email' => trim($params['email']),
            'input_status' => $studentInfo['is_new'] ? AbroadLaunchLeadsInputRecordModel::INPUT_STATUA_SUCCESS : AbroadLaunchLeadsInputRecordModel::INPUT_STATUS_REPEAT,
            'create_time' => time(),
        ]);
        // 返回结果
        $returnData['record_id'] = !empty($recordId) ? $recordId : 0;
        $returnData['code'] = $studentInfo['is_new'] ? 'success' : 'repeat';
        return $returnData;
    }
}