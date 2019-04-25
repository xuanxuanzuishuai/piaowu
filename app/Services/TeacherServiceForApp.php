<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/21
 * Time: 12:14
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Models\TeacherModelForApp;


class TeacherServiceForApp
{

    public static function registerTeacherInUserCenter($name, $mobile, $uuid = '', $birthday = '', $gender = '')
    {
        $userCenter = new UserCenter(UserCenter::AUTH_APP_ID_AIPEILIAN_TEACHER, 'b56a214222a8420e');
        $authResult = $userCenter->teacherAuthorization($mobile, $name, $uuid, $birthday, $gender);
        return $authResult;
    }

    /**
     * @param $mobile
     * @param $name
     * @return array|null
     */
    public static function teacherRegister($mobile, $name)
    {
        $result = self::registerTeacherInUserCenter($name, $mobile);
        if (empty($result['uuid'])) {
            SimpleLogger::info(__FILE__ . __LINE__, $result);
            return null;
        }

        $uuid = $result['uuid'];
        $lastId = self::addTeacher($mobile, $uuid, $name);

        if (empty($lastId)) {
            SimpleLogger::info(__FILE__ . __LINE__, [
                'msg' => 'user reg error, add new user error.',
            ]);
            return null;
        }

        return $lastId;
    }

    /**
     * @param $mobile
     * @param $uuid
     * @param $name
     * @return int|mixed|null|string
     */
    public static function addTeacher($mobile, $uuid, $name)
    {
        $user = [
            'uuid'           => $uuid,
            'mobile'         => $mobile,
            'name'           => $name,
            'create_time'    => time(),
            'status'     => TeacherModelForApp::ENTRY_REGISTER,
            'is_export'  => 0,
        ];

        $id = TeacherModelForApp::insertRecord($user);

        return $id == 0 ? null : $id;
    }
}