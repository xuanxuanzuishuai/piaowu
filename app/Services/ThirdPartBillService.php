<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2020/7/10
 * Time: 下午3:04
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\StudentModel;
use App\Models\ThirdPartBillModel;
use App\Services\Queue\ThirdPartBillTopic;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ThirdPartBillService
{
    public static function checkDuplicate($filename, $operatorId)
    {
        try {
            $fileType = ucfirst(pathinfo($filename)["extension"]);
            $reader = IOFactory::createReader($fileType);
            $spreadsheet = $reader->load($filename);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $now = time();
            $data = [];
            foreach($sheetData as $v) {
                if(empty($v)) {
                    continue;
                }
                $A = trim($v['A']);
                if(trim($v['C']) != ThirdPartBillModel::IGNORE && is_numeric($A)) {
                    $data[] = [
                        'mobile'      => $A,
                        'trade_no'    => trim($v['B']),
                        'operator_id' => $operatorId,
                        'pay_time'    => $now,
                        'create_time' => $now,
                    ];
                }
            }
            // 检查所有的手机号是否合法, 并返回所有错误的记录
            $invalidMobiles = [];
            foreach($data as $v) {
                if(!Util::isChineseMobile($v['mobile'])) {
                    $invalidMobiles[] = $v;
                }
            }
            if(count($invalidMobiles) > 0) {
                return new RunTimeException(['invalid_mobile', 'import'], ['list' => $invalidMobiles]);
            }
        } catch (\Exception $e) {
            return new RunTimeException([$e->getMessage()]);
        }

        // 学生手机号重复
        if(count($data) != count(array_unique(array_column($data, 'mobile')))) {
            return new RunTimeException(['mobile_repeat', 'import']);
        }

        // 检查是否已经有发货记录
        $records = PayServices::trialedUserByMobile(array_column($data, 'mobile'));
        if(!empty($records)) {
            return new RunTimeException(['has_trialed_records', 'import'], ['list' => $records]);
        }

        return $data;
    }

    public static function handleImport($params)
    {
        $data = [
            'mobile'            => $params['mobile'],
            'trade_no'          => $params['trade_no'],
            'pay_time'          => $params['pay_time'],
            'package_id'        => $params['package_id'],
            'parent_channel_id' => $params['parent_channel_id'],
            'channel_id'        => $params['channel_id'],
            'operator_id'       => $params['operator_id'],
            'is_new'            => ThirdPartBillModel::NOT_NEW,
            'create_time'       => time(),
        ];

        $student = StudentModel::getRecord(['mobile' => $params['mobile']]);

        // 手机号不存在时注册新用户
        if(empty($student)) {
            $result = StudentServiceForApp::studentRegister(strval($params['mobile']), $params['channel_id']);
            if(empty($result)) {
                $data['status'] = ThirdPartBillModel::STATUS_FAIL;
                $data['reason'] = 'register student failed';
                $data['student_id'] = 0;
                return ThirdPartBillModel::insertRecord($data, false);
            } else {
                list($studentId, $isNew) = $result;
                $data['student_id'] = $studentId;
                $data['is_new'] = $isNew ? ThirdPartBillModel::IS_NEW : ThirdPartBillModel::NOT_NEW;
            }
        } else {
            $data['student_id'] = $student['id'];
        }

        $erp = new Erp();

        // 通知ERP创建订单
        list($result, $body) = $erp->createDeliverBill([
            'student_mobile'  => $params['mobile'],
            'trade_no'        => $params['trade_no'],
            'end_time'        => $params['pay_time'],
            'source'          => 2, // 手工创建订单
            'package_id'      => $data['package_id'],
            'pay_channel'     => 11, // 第三方支付渠道
            'description'     => 'DSS表格导入订单',
            'supplement_type' => 1, // 新单
            'deliver_now'     => 1, // 立即发货
        ]);

        // 记录请求结果
        if($result === false) {
            $data['reason'] = $body;
            $data['status'] = ThirdPartBillModel::STATUS_FAIL;
        } else {
            $data['status'] = ThirdPartBillModel::STATUS_SUCCESS;
        }

        return ThirdPartBillModel::insertRecord($data, false);
    }

    public static function sendMessages($data)
    {
        $queue = new ThirdPartBillTopic();
        foreach($data as $v) {
            $queue->import($v)->publish();
        }
    }

    public static function list($params, $page, $count)
    {
        list($total, $records) = ThirdPartBillModel::list($params, $page, $count);

        foreach($records as $k => $v) {
            $v['status_zh'] = DictConstants::get(DictConstants::THIRD_PART_BILL_STATUS, $v['status']);
            $records[$k] = $v;
        }

        return [$total, $records];
    }
}