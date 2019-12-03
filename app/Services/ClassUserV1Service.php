<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/12/2
 * Time: 下午4:42
 */

namespace App\Services;


use App\Models\ClassV1UserModel;

class ClassUserV1Service
{
    public static function selectClassesByUser($userId, $userRole)
    {
        return ClassV1UserModel::selectClassesByUser($userId, $userRole);
    }

    public static function selectStudentsByClass($classId)
    {
        return ClassV1UserModel::selectStudentsByClass($classId);
    }
}