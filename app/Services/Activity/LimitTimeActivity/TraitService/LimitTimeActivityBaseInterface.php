<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

interface LimitTimeActivityBaseInterface
{
    /**
     * 学生付费状态检测
     * @return array
     */
    public function studentPayStatusCheck(): array;

    /**
     * 学生付费状态汉化
     * @param int $studentStatus
     * @return string
     */
    public function studentPayStatusZh(int $studentStatus): string;

    /**
     * 学生邀请购买体验卡人数（智能）/创建转介绍关系数量（真人）
     * @return int
     */
    public function getStudentReferralOrBuyTrailCount(): int;

    /**
     * 根据uuid批量获取用户信息
     * @param array $uuids
     * @param array $fields
     * @return array
     */
    public function getStudentInfoByUUID(array $uuids, array $fields = []): array;

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
     * @param $limitArr
     * @return array
     */
    public function getStudentInfoByName(string $name, array $fields = [], $limitArr = [0,1000]): array;

    /**
     * 根据员工id获取多个员工信息，并且以员工id为key
     * @param array $employeeIds
     * @return array
     */
    public function getEmployeeInfo(array $employeeIds): array;
}