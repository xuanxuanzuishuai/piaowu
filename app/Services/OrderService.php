<?php


namespace App\Services;


use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssStudentModel;

class OrderService
{
    /**
     * app下单
     * @param $params
     * @return array|mixed
     * @throws RunTimeException
     */
    public static function createAppBill($params)
    {
        $params['gift_goods']        = $params['gift_goods'] ?? null;
        $params['callback_url']      = $params['callback_url'] ?? null;
        $params['student_coupon_id'] = $params['student_coupon_id'] ?? null;

        $studentInfo = DssStudentModel::getRecord(['id' => $params['student_id']], ['uuid']);
        if (empty($studentInfo)) {
            throw new RunTimeException(['student_not_exist']);
        }
        $params['uuid'] = $studentInfo['uuid'];
        $result = (new Dss())->createAppBill($params);
        if (!empty($result)) {
            $result['order_id'] = $result['bill'];
        }
        return $result;
    }
}