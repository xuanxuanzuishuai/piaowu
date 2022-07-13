<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

interface LimitTimeActivityBaseInterface
{
    /**
     * 根据uuid批量获取用户信息
     * @param array $uuids
     * @param array $fields
     * @return array
     */
    public static function getStudentInfoByUUID(array $uuids, array $fields = []): array;

    /**
     * 根据手机号获取用户信息
     * @param array $mobiles
     * @param array $fields
     * @return array
     */
    public static function getStudentInfoByMobile(array $mobiles, array $fields = []): array;

    /**
     * 根据学生名称模糊搜索用户信息
     * @param string $name
     * @param array $fields
     * @return array
     */
    public static function getStudentInfoByName(string $name, array $fields = []): array;

}