<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/6/18
 * Time: 5:27 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Models\ApprovalConfigModel;
use App\Models\RoleModel;

class ApprovalConfigService
{
    public static function addConfig($type, $levels, $roles, $operator)
    {
        $currentConfig = self::getValidConfig($type);
        if (!empty($currentConfig)) {
            return ['has_valid_config'];
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        if ($levels != count($roles)) {
            return ['roles_error'];
        }

        foreach ($roles as $roleId) {
            $role = RoleModel::getById($roleId);
            if (empty($role)) {
                return ['roles_error'];
            }
        }

        $id = ApprovalConfigModel::insertRecord([
            'type' => $type,
            'levels' => $levels,
            'roles' => implode(',', $roles),
            'create_time' => time(),
            'operator' => $operator,
            'status' => Constants::STATUS_TRUE
        ], true);

        if (empty($id)) {
            return ['insert_failure'];
        }

        return [null, $id];
    }

    public static function discardConfig($id)
    {
        $config = ApprovalConfigModel::getById($id);
        if (empty($config)) {
            return 'approval_config_not_found';
        }

        if ($config['status'] == Constants::STATUS_FALSE) {
            return 'invalid_status';
        }

        $count = ApprovalConfigModel::updateRecord($id, [
            'status' => Constants::STATUS_FALSE
        ], true);

        if ($count <= 0) {
            return 'update_failure';
        }

        return null;
    }

    public static function getValidConfig($type)
    {
        $validConfig = ApprovalConfigModel::getRecord([
            'type' => $type,
            'status' => Constants::STATUS_TRUE
        ], '*', true);

        return $validConfig;
    }
}