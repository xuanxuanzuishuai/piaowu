<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:05 PM
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Models\OrgAccountModel;
use App\Models\OrganizationModel;
use App\Models\OrganizationModelForApp;
use App\Models\OrgLicenseModel;

class OrganizationServiceForClassroom
{
    const ORG_SECURITY_KEY = "dkUwIxVLchlXB9Unvr68dJoT";

    /**
     * 机构登录
     * @param $account
     * @param $password
     * @return array
     * @throws RunTimeException
     */
    public static function login($account, $password)
    {
        $orgAccount = OrgAccountModel::getByAccount($account);
        if (empty($orgAccount)) {
            throw new RunTimeException(['org_account_invalid']);
        }

        if ($orgAccount['status'] != OrgAccountModel::STATUS_NORMAL) {
            throw new RunTimeException(['org_account_invalid']);
        }

        if ($password != $orgAccount['password']) {
            throw new RunTimeException(['org_account_password_error']);
        }

        $orgId = $orgAccount['org_id'];
        $orgInfo = self::getOrgInfo($orgId);

        if ($orgInfo['status'] == OrganizationModel::STATUS_STOP) {
            throw new RunTimeException(['org_is_disabled']);
        }

        $orgInfo['account'] = $account;
        $licenseInfo = OrgLicenseService::getLicenseInfo($orgId, OrgLicenseModel::TYPE_CLASSROOM);
        $orgInfo['license_num'] = $licenseInfo['valid_num'];
        $orgInfo['start_time'] = (string)$licenseInfo['min_active_time'];
        $orgInfo['end_time'] = (string)$licenseInfo['max_expire_time'];

        $token = OrganizationModelForApp::genToken($orgId);
        OrganizationModelForApp::setOrgToken($orgId, $account, $token);

        OrgAccountModel::updateRecord($orgAccount['id'], ['last_login_time' => time()], false);

        $loginData = [
            'org_info' => $orgInfo,
            'org_token' => $token
        ];

        return $loginData;
    }

    /**
     * token登录
     *
     * @param string $account 手机号
     * @param string $token 登录返回的token
     * @return array [0]errorCode [1]登录数据
     * @throws RunTimeException
     */
    public static function loginWithToken($account, $token)
    {
        $cache = OrganizationModelForApp::getOrgCacheByToken($token);
        if (empty($cache) || empty($cache['account']) || $cache['account'] != $account) {
            throw new RunTimeException(['invalid_org_token']);
        }

        $orgAccount = OrgAccountModel::getByAccount($account);
        if (empty($orgAccount)) {
            throw new RunTimeException(['org_account_invalid']);
        }

        if ($orgAccount['status'] != OrgAccountModel::STATUS_NORMAL) {
            throw new RunTimeException(['org_account_invalid']);
        }

        $orgId = $orgAccount['org_id'];
        $orgInfo = self::getOrgInfo($orgId);

        if ($orgInfo['status'] == OrganizationModel::STATUS_STOP) {
            throw new RunTimeException(['org_is_disabled']);
        }

        $orgInfo['account'] = $account;
        $licenseInfo = OrgLicenseService::getLicenseInfo($orgId, OrgLicenseModel::TYPE_CLASSROOM);
        $orgInfo['license_num'] = $licenseInfo['valid_num'];
        $orgInfo['start_time'] = (string)$licenseInfo['min_active_time'];
        $orgInfo['end_time'] = (string)$licenseInfo['max_expire_time'];

        $loginData = [
            'org_info' => $orgInfo,
            'org_token' => $token
        ];

        return $loginData;
    }

    public static function getOrgInfo($orgId)
    {
        $org = OrganizationModelForApp::getById($orgId);
        if (empty($org)) {
            return [];
        }
        $orgInfo = [
            "id" => $org['id'],
            "name" => $org['name'],
            "start_time" => $org['start_time'],
            "end_time" => $org['end_time'],
            "status" => $org['status']
        ];

        return $orgInfo;
    }
}
