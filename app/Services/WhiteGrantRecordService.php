<?php
/**
 * 白名单发放
 * User: yangpeng
 * Date: 2021/8/13
 * Time: 10:35 AM
 */

namespace App\Services;

use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\WeChatPackage;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssWechatOpenIdListModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\UserWeiXinModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WhiteGrantRecordModel;

class WhiteGrantRecordService
{

    /**
     * 获取发放列表
     * @param $params
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function list($params, $page, $pageSize){
        $where = [];
        if(!empty($params['uuid'])){
            $where['uuid'] = $params['uuid'];
        }

        if(!empty($params['status'])){
            $where['status'] = $params['status'];
        }

        if(!empty($params['mobile'])){
            $studentId = DssStudentModel::getRecord(['mobile'=>$params['mobile']],['id','']);
            $where['student_id'] = $studentId ?? 0;
        }

        if(!empty($params['course_manage_id'])){
            $where['course_manage_id'] = $params['course_manage_id'];
        }

        $total = WhiteGrantRecordModel::getCount($where);

        if ($total <= 0) {
            return [[], 0];
        }

        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];

        $list = WhiteGrantRecordModel::getRecords($where);


        $list = WeekWhiteListService::initList($list);

        return compact('list', 'total');
    }

    /**
     * 更新发放状态
     * @param $params
     * @return array|int[]
     */
    public static function updateGrantRecord($params){
        $item = WhiteGrantRecordModel::getRecord(['id'=>$params['id']]);
        if(empty($item)){
            return Valid::addErrors([], 'updateGrant', 'record_not_found');
        }

        if($item['status'] != WhiteGrantRecordModel::STATUS_GIVE_FAIL){
            return Valid::addErrors([], 'updateGrant', 'nothing_change');
        }

        $data = [
            'status' => WhiteGrantRecordModel::STATUS_GIVE_NOT_GRANT,
            'remark' => $params['remark'],
            'update_time'=>time(),
        ];

        $res = WhiteGrantRecordModel::updateRecord($params['id'], $data);

        if(!$res){
            return Valid::addErrors([], 'updateGrant', 'update_failure');
        }

        return ['code'=>0];
    }

    /**
     * 手动发放
     * @param $params
     * @return array
     */
    public static function manualGrant($params){
        $grantInfo = WhiteGrantRecordModel::getRecord(['id'=>$params['id']]);
        if(empty($grantInfo)){
            return Valid::addErrors([], 'updateGrant', 'record_not_found');
        }

        if($grantInfo['status'] != WhiteGrantRecordModel::STATUS_GIVE_FAIL){
            return Valid::addErrors([], 'updateGrant', 'nothing_change');
        }

        $student = DssStudentModel::getRecord(['uuid'=>$grantInfo['uuid']]);

        $nextData['student'] = $student;

        try{
            switch ($grantInfo['grant_step']){
                case WhiteGrantRecordModel::GRANT_STEP_1:
                case WhiteGrantRecordModel::GRANT_STEP_2:

                    $task_info = json_decode($grantInfo['task_info'], true);

                    $list = self::getAwardList($task_info['fail']);

                    if(empty($list)){
                        return Valid::addErrors([], 'manualGrant', 'emptyData');
                    }

                    $nextData['grantInfo'] = $grantInfo;
                    $nextData['uuid'] = $grantInfo['uuid'];
                    $nextData['list'] = $list;

                    WhiteGrantRecordService::grant($nextData);
                    break;
                case WhiteGrantRecordModel::GRANT_STEP_3:
                    $nextData = [
                        'student' => ['uuid' => $grantInfo['uuid']],
                        'award_num'   => $grantInfo['grant_money'],
                        'operator_uuid' => $params['operator_id']
                    ];
                    WhiteGrantRecordService::deduction($nextData);
                    break;
                case WhiteGrantRecordModel::GRANT_STEP_4:
                    $nextData = [
                        'student' => ['uuid' => $grantInfo['uuid']]
                    ];
                    WhiteGrantRecordService::getBindInfo($nextData);
                    break;
                case WhiteGrantRecordModel::GRANT_STEP_5:
                    $userWeixin = UserWeiXinModel::getRecord(['user_id' => $student['id']],['status','open_id']);
                    $nextData['open_id'] = $userWeixin['open_id'];
                    WhiteGrantRecordService::weixinToAccount($nextData);
                    break;
                default:
                    return Valid::addErrors([], 'manualGrant', 'stepNotDefind');
            }
        }catch (RuntimeException $e){
            self::create($student, $e->getData(), WhiteGrantRecordModel::STATUS_GIVE_FAIL);
            return ['code'=>1];
        }
        return ['code'=>0];
    }

    /**
     * 自动发放
     * @param $uuid
     * @param $list
     * @return false
     */
    public static function grant($next){


        if(empty($next['student']) || $next['student']['status'] != DssStudentModel::STATUS_NORMAL){
            $gold_leaf_ids = array_column($next['list'], 'id');
            $taskArr['fail'] = $gold_leaf_ids;
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_1,'award_num' => 0, 'msg' => '用户账号异常', 'taskArr'=> $taskArr]);
        }
        //调用erp
        self::sendDataToErp($next);
    }


    /**
     * 修改发放状态为已发放
     * @param $uuid
     * @param $list
     * @return array
     */
    public static function sendDataToErp($next){
        $taskArr = [];
        $award_num = $next['grantInfo']['grant_money'] ?? 0;
        foreach ($next['list'] as $one){
            //修改状态为已发放
            $res = (new Erp())->addEventTaskAward($next['uuid'], $one['event_task_id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE);

            if ($res['code'] == Valid::CODE_SUCCESS) {
                $taskArr['succ'][] = $one['id'];
                $award_num += $one['award_num'];
            }else{
                SimpleLogger::error('ERP_CREATE_USER_EVENT_TASK_AWARD_FAIL', [$one]);
                $taskArr['fail'][] = $one['id'];
            }
        }

        //2.金叶子发放失败
        if(!empty($taskArr['fail'])){
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_2,'award_num' => $award_num, 'msg' => '金叶子发放失败', 'taskArr' => $taskArr]);
        }

        $next['award_num'] = $award_num;

        self::deduction($next);

    }

    //微信转账
    public static function weixinToAccount($next){

        $wx = new WeChatPackage(8,1,1);

        $mchBillNo = 'ym' . time();
        $actName  = '测试发红包';
        $sendName = '测试商户名称';
        $openId = $next['open_id'];
        $value = 1;
        $wishing = '祝福语';

        $resultData = $wx->sendPackage($mchBillNo, $actName, $sendName, $openId, $value, $wishing, 'redPack');

        if(trim($resultData['result_code']) != WeChatAwardCashDealModel::RESULT_SUCCESS_CODE) {
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_5,'award_num' => $next['award_num'] * 100, 'msg' =>$resultData['return_msg']]);
        }


        $data = [
            'msg' => '发放成功',
            'is_bind_wx' => WhiteGrantRecordModel::BIND_WX_NORMAL,
            'is_bind_gzh' => WhiteGrantRecordModel::BIND_GZH_NORMAL,
            'step'=>WhiteGrantRecordModel::GRANT_STEP_0,
        ];

        self::create($next['student'], $data,WhiteGrantRecordModel::STATUS_GIVE);
    }

    /**
     * 扣减
     * @param $uuid
     * @param $award_num
     * @param $operator_uuid
     * @return bool
     */
    public static function deduction($next){
        $data = [
            'uuid' => $next['student']['uuid'],
            'datatype' => ErpStudentAccountModel::DATA_TYPE_LEAF,
            'num'   => $next['award_num'],
            'source_type' => Erp::SOURCE_TYPE_OP_TO_MONEY,
            'operator_uuid' => $next['operator_uuid'],
            'remark'    => 'OP系统扣减兑换现金',
        ];

        $res = (new Erp())->reduce_account($data);
        if(!$res){
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_3,'award_num' => $next['award_num'], 'msg' => '金叶子扣减失败']);
        }

        $next['award_num'] = $next['award_num'] / 100;
        self::getBindInfo($next);
    }

    /**
     * 创建
     * @param $student
     * @param $award_num
     * @param $status
     * @param $msg
     * @param int $operator_id
     */
    public static function create($student, $data, $status){

        $now = time();
        $insert = [
            'uuid'              => $student['uuid'] ?? '',
            'grant_money'       => $data['award_num'],
            'status'            => $status,
            'remark'            => $data['msg'],
            'is_bind_wx'        => $data['is_bind_wx'] ?? '-',
            'is_bind_gzh'       => $data['is_bind_gzh'] ?? '-',
            'task_info'         => json_encode($data['taskArr'] ?? []),
            'course_manage_id'  => $student['course_manage_id'] ?? 0,
            'grant_step'        => $data['step'],
            'operator_id'       => 0,
            'grant_time'        => $status == WhiteGrantRecordModel::STATUS_GIVE ? $now : 0,
            'create_time'       => $now,
            'update_time'       => 0,
        ];

        if(isset($data['nextData']['grantInfo']['id'])){
            $id = $data['nextData']['grantInfo']['id'];
            WhiteGrantRecordModel::updateRecord($id, $insert);
        }else{
            WhiteGrantRecordModel::insertRecord($insert);
        }

    }

    /**
     * 获取绑定微信、公众号等信息
     * @param $student
     * @return array
     */
    public static function getBindInfo($next){
        $is_bind_wx = WhiteGrantRecordModel::BIND_WX_DESIABLE;
        $is_bing_gzh = WhiteGrantRecordModel::BIND_GZH_DESIABLE;

        //是否绑定微信
        $userWeixin = UserWeiXinModel::getRecord(['user_id' => $next['student']['id']],['status','open_id']);
        if($userWeixin && $userWeixin['status'] == UserWeiXinModel::STATUS_NORMAL){
            $is_bind_wx = WhiteGrantRecordModel::BIND_WX_NORMAL;

            //是否绑定公众号
            $wechat = DssWechatOpenIdListModel::getRecord(['openid' => $userWeixin['open_id']], ['status']);
            if($wechat && $wechat['status'] == DssWechatOpenIdListModel::SUBSCRIBE_WE_CHAT){
                $is_bing_gzh = WhiteGrantRecordModel::BIND_GZH_NORMAL;
            }
        }else{
            $is_bing_gzh = '-';
        }

        if($is_bind_wx != WhiteGrantRecordModel::BIND_WX_NORMAL || $is_bing_gzh != WhiteGrantRecordModel::BIND_GZH_NORMAL){
            $msg = $is_bind_wx != WhiteGrantRecordModel::BIND_WX_NORMAL ? '未绑定微信' : '未关注公众号';
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_4, 'award_num' => 0, 'msg' => $msg , 'is_bind_wx' => $is_bind_wx, 'is_bind_gzh'=>$is_bing_gzh]);
        }

        $next['open_id'] = $userWeixin['open_id'];

        //5.微信转账
        self::weixinToAccount($next);

    }

    public static function getAwardList($ids = []){

        $keyCode = 'REISSUE_PIC_WORD';
        list($actName, $sendName, $wishing) = CashGrantService::getRedPackConfigWord($keyCode);
        $a = [$actName, $sendName, $wishing];
        echo json_encode($a,256);die;

        $s = strtotime(date('Y-m-01', strtotime('-1 month')));
        $e = strtotime('-1 day', strtotime(date('Y-m-01 23:59:59')));

        $where = [
            'status'=>ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING,
            'award_node' => 'award_node',
            "create_time[<>]" => [$s, $e],
        ];

        if(!empty($ids)){
            $where['id'] = $ids;
        }

        $fields = [
            'id',
            'uuid',
            'award_num',
            'event_task_id'
        ];

        $list = ErpUserEventTaskAwardGoldLeafModel::getRecords($where, $fields);

        $studentList = [];

        foreach ($list as $one){
            $studentList[$one['uuid']][] = $one;
        }

        return $studentList;
    }
}
