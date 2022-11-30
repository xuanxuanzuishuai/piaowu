<?php

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Excel\ExcelImportFormat;
use App\Libs\Exceptions\RunTimeException;
use App\Models\BAApplyModel;
use App\Models\BAListModel;
use App\Models\BaWeixinModel;
use App\Models\EmployeeModel;
use App\Models\RoleModel;
use App\Models\ShopInfoModel;

class BAService
{
    /**
     * 获取BA的申请列表
     * @param $employeeId
     * @return array|null
     */
    public static function getBAApplyList($employeeId, $parmas, $page, $count)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);

        $list = [];
        if ($employeeInfo['role_id'] == RoleModel::BA_MANAGE) {
            list($list, $totalCount) = BAApplyModel::getBaManageApplyList($employeeId, $parmas, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::REGION_MANAGE) {
            list($list, $totalCount) = BAApplyModel::getRegionManageApplyList($employeeId, $parmas, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::SUPER_ADMIN) {
            list($list, $totalCount) = BAApplyModel::getSuperApplyList($parmas, $page, $count);
        }
         return [$list, $totalCount];
    }

    /**
     * 导出BA的申请列表
     * @param $employeeId
     * @return array|null
     */
    public static function exportData($employeeId, $parmas)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
        $count = BAApplyModel::getCount(['id[>=]' => 1]);

        $list = [];
        if ($employeeInfo['role_id'] == RoleModel::BA_MANAGE) {
            list($list, $totalCount) = BAApplyModel::getBaManageApplyList($employeeId, $parmas, 1, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::REGION_MANAGE) {
            list($list, $totalCount) = BAApplyModel::getRegionManageApplyList($employeeId, $parmas, 1, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::SUPER_ADMIN) {
            list($list, $totalCount) = BAApplyModel::getSuperApplyList($parmas, 1, $count);
        }



        $title = [
            '手机号',
            '姓名',
            '身份证号',
            '工号',
            '门店编号',
            '注册时间',
            '状态',
            '微信open_id'
        ];

        $dataResult = [];
        foreach($list as $v) {
            $dataResult[] = [
                'mobile' => $v['mobile'],
                'name' => $v['name'],
                'idcard' => $v['idcard'],
                'job_number' => $v['job_number'],
                'shop_number' => $v['shop_number'],
                'create_time' => date('Y-m-d H:i:s', $v['create_time']),
                'status_msg' => BAApplyModel::STATUS_MSG[$v['check_status']],
                'open_id' => $v['open_id'] ?? ''

            ];
        }

        $fileName =  '(' . date("Y-m-d H:i:s") . '_'.mt_rand(1, 100) . ')BA列表.xlsx';
        $tmpFileSavePath = ExcelImportFormat::createExcelTable($dataResult, $title,
            ExcelImportFormat::OUTPUT_TYPE_SAVE_FILE);
        $ossPath = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_TMP_EXCEL . '/' . $fileName;
        AliOSS::uploadFile($ossPath, $tmpFileSavePath);
        unlink($tmpFileSavePath);

        $ossPath = AliOSS::signUrls($ossPath, "", "", "", true);

        return $ossPath;
    }

    /**
     * 处理ba申请
     * @param $employeeId
     * @param $params
     * @throws RunTimeException
     */
    public static function updateApply($employeeId, $params)
    {
        $arr = explode(',', $params['ids']);
        $checkStatus = $params['check_status'];

        $allApplyInfo = BAApplyModel::getRecords(['id' => $arr]);
        $allApplyInfoStatus = array_column($allApplyInfo, 'check_status');

        //已经处理过的不可再次处理
        if (in_array(BAApplyModel::APPLY_PASS, $allApplyInfoStatus) || in_array(BAApplyModel::APPLY_REJECT, $allApplyInfoStatus)) {
            throw new RuntimeException(["not_allow_update_has_update_apply"]);
        }

        foreach ($allApplyInfo as $value) {
            //已经存在的工号，不可再次入表BA
            $res = BAListModel::getRecord(['job_number' => $value['job_number']]);
            if (empty($res)) {
                BAListModel::insertRecord(
                    [
                        'id' => $value['id'],
                        'mobile' => $value['mobile'],
                        'name' => $value['name'],
                        'job_number' => $value['job_number'],
                        'create_time' => $value['create_time'],
                        'shop_id' => $value['shop_id']
                    ]
                );
            }
        }

        BAApplyModel::batchUpdateRecord(['check_status' => $checkStatus, 'operator_employee' => $employeeId, 'update_time' => time()], ['id' => $arr]);
    }

    /**
     * BA的info
     * @param $baId
     * @return mixed
     */
    public static function getBaInfo($baId)
    {
        $baInfo = BAListModel::getBaInfo($baId)[0];
        return $baInfo;
    }

    /**
     * 获取已通过的BA
     * @param $employeeId
     * @return array|null
     */
    public static function getPassBa($employeeId, $page, $count)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);


        $list = [];
        if ($employeeInfo['role_id'] == RoleModel::BA_MANAGE) {
            list($list, $totalCount) = BAListModel::getBaManageApplyList($employeeId, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::REGION_MANAGE) {
            list($list, $totalCount) = BAListModel::getRegionManageApplyList($employeeId, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::SUPER_ADMIN) {
            list($list, $totalCount) = BAListModel::getSuperApplyList($page, $count);
        }
        return [$list, $totalCount];
    }


    /**
     * 新增/编辑申请
     * @param $openId
     * @param $params
     * @return mixed
     * @throws RunTimeException
     */
    public static function addApply($openId, $params)
    {

        $shopInfo = ShopInfoModel::getRecord(['id' => $params['shop_id']]);
        if (empty($shopInfo)) {
            throw new RunTimeException(['shop_is_not_exist']);
        }


        $where = [
            'OR' => [
                'mobile' => $params['mobile'],
                'job_number' => $params['job_number']
            ],
            'open_id[!]' => $openId
        ];

        $openidExistApply = BAApplyModel::getRecord($where);


        //手机号，工号，open_id需要是一比一的对应关系，任何一个和其他人冲突，都不允许

        if (!empty($openidExistApply)) {
            throw new RunTimeException(['mobile_job_number_open_id_not_allow_repeat']);
        }

        //当前open_id是否有信息
        $res = BAApplyModel::getRecord(['open_id' => $openId]);


        $data = [
            'mobile' => $params['mobile'],
            'name' => $params['name'],
            'shop_id' => $params['shop_id'],
            'job_number'=> $params['job_number'],
            'update_time' => time(),
            'idcard' => $params['idcard'],
            'open_id' => $openId,
            'check_status' => BAApplyModel::APPLY_WAITING
        ];

        if (empty($res)) {
            $data['create_time'] = time();
            BAApplyModel::insertRecord($data);
        } else {
            BAApplyModel::updateRecord($res['id'], $data);
        }

        return BAApplyModel::getRecord(['open_id' => $openId]);

    }


    /**
     * 申请的BA信息
     * @param $baInfo
     * @return array
     */
    public static function getBaApplyInfo($baInfo)
    {
        return BAApplyModel::getBaInfo($baInfo['ba_id']);
    }
}