<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/6/10
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class AgentOrganizationStudentModel extends Model
{
    public static $table = "agent_organization_student";

    const STATUS_NORMAL = 1;
    const STATUS_DISABLE = 2;


    /**
     * 批量添加修改操作
     *
     * @param array $insertStudentInfo
     * @param array $updateStudentInfo
     * @return bool
     */
    public static function batchOperator(array $insertStudentInfo = [], array $updateStudentInfo = [])
    {
        if (!empty($insertStudentInfo)){
            $insertRow = self::batchInsert($insertStudentInfo);
            if (empty($insertRow)) {
                SimpleLogger::error('insert agent organization student data error', $insertStudentInfo);
                return false;
            }
        }

        if (!empty($updateStudentInfo)){
            $updateRow = self::batchUpdateRecord($updateStudentInfo['data'], $updateStudentInfo['where']);
            if (empty($updateRow)) {
                SimpleLogger::error('update agent organization student data error', $updateStudentInfo);
                return false;
            }
        }
        return true;
    }

}