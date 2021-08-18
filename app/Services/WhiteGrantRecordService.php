<?php
/**
 * 白名单发放
 * User: yangpeng
 * Date: 2021/8/13
 * Time: 10:35 AM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\WeChatPackage;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Dss\DssWechatOpenIdListModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\UserWeiXinModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WhiteGrantRecordModel;
use function AlibabaCloud\Client\json;

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

        if(!empty($params['id'])){
            $where['id'] = $params['id'];
        }

        if(!empty($params['mobile'])){
            $studentInfo = DssStudentModel::getRecord(['mobile'=>$params['mobile']],['id','uuid','mobile']);
            $where['uuid'] = $studentInfo['uuid'] ?? 0;
        }

        if(!empty($params['course_manage_id'])){
            $where['course_manage_id'] = $params['course_manage_id'];
        }

        $total = WhiteGrantRecordModel::getCount($where);

        if ($total <= 0) {
            return ['list'=>[], 'total'=>0];
        }

        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];

        $list = WhiteGrantRecordModel::getRecords($where);
        $uuids = array_column($list, 'uuid');

        $wechatSubscribeInfo = DssWechatOpenIdListModel::getUuidOpenIdInfo($uuids);
        $wechatSubscribeInfo = array_column($wechatSubscribeInfo, null, 'uuid');

        foreach ($list as &$one){
            $one['is_bind_wx'] = $wechatSubscribeInfo[$one['uuid']]['bind_status'] ?? DssUserWeiXinModel::STATUS_DISABLE;
            $one['is_bind_gzh'] = $wechatSubscribeInfo[$one['uuid']]['subscribe_status'] ?? DssWechatOpenIdListModel::UNSUBSCRIBE_WE_CHAT;
        }

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
     * @return array|int[]
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
        $nextData['grantInfo'] = $grantInfo;
        $nextData['awardNum']   = $grantInfo['grant_money'] / 100;
        $nextData['operator_uuid'] = $params['operator_id'];
        $nextData['awardIds'] = explode(',', $grantInfo['award_ids']);
        $nextData['uuid'] = $grantInfo['uuid'];
        try{
            switch ($grantInfo['grant_step']){
                case WhiteGrantRecordModel::GRANT_STEP_1:
                case WhiteGrantRecordModel::GRANT_STEP_2:

                    $task_info = json_decode($grantInfo['task_info'], true);

                    $list = self::getAwardList($task_info['fail']);

                    if(empty($list)){
                        return Valid::addErrors([], 'manualGrant', 'emptyData');
                    }

                    $nextData['list'] = $list[$grantInfo['uuid']];
                    WhiteGrantRecordService::grant($nextData);
                    break;
                case WhiteGrantRecordModel::GRANT_STEP_3:
                    WhiteGrantRecordService::deduction($nextData);
                    break;
                case WhiteGrantRecordModel::GRANT_STEP_4:
                    WhiteGrantRecordService::getBindInfo($nextData);
                    break;
                case WhiteGrantRecordModel::GRANT_STEP_5:
                    $wechatSubscribeInfo = DssWechatOpenIdListModel::getUuidOpenIdInfo([$student['uuid']]);
                    $nextData['open_id'] = $wechatSubscribeInfo[0]['open_id'];
                    WhiteGrantRecordService::sendPackage($nextData);
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
     * 发放
     * @param $next
     * @throws RunTimeException
     */
    public static function grant($next){

        if(empty($next['student']) || $next['student']['status'] != DssStudentModel::STATUS_NORMAL){
            $gold_leaf_ids = array_column($next['list'], 'id');
            $taskArr['fail'] = $gold_leaf_ids;
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_1,'awardNum' => 0, 'msg' => '用户账号异常', 'taskArr'=> $taskArr]);
        }
        //调用erp
        self::sendDataToErp($next);
    }


    /**
     * 修改发放状态为已发放
     * @param $next
     * @throws RunTimeException
     */
    public static function sendDataToErp($next){
        $taskArr = [];
        $awardNum = $next['grantInfo']['grant_money'] ?? 0;
        $awardIds = $next['grantInfo']['award_ids'] ?? [];

        if($awardIds){
            $awardIds = explode(',', $awardIds);
        }

        foreach ($next['list'] as $one){
            //修改状态为已发放
            $res = (new Erp())->addEventTaskAward($next['uuid'], $one['event_task_id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE, $one['id']);
            if ($res['code'] == Valid::CODE_SUCCESS) {
                $taskArr['succ'][] = $one['id'];
                $awardNum += $one['award_num'];
            }else{
                SimpleLogger::error('ERP_CREATE_USER_EVENT_TASK_AWARD_FAIL', [$one]);
                $taskArr['fail'][] = $one['id'];
            }
            $awardIds[] = $one['id'];
        }

        //2.金叶子发放失败
        if(!empty($taskArr['fail'])){
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_2,'awardNum' => $awardNum, 'msg' => '金叶子发放失败', 'taskArr' => $taskArr]);
        }

        $next['awardNum'] = $awardNum;
        $next['awardIds'] = $awardIds;
        self::deduction($next);

    }

    /**
     * 微信发放红包
     * @param $next
     * @throws RunTimeException
     */
    public static function sendPackage($next){

        $appId    = Constants::SMART_APP_ID;
        $busiType = DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER;
        $wx = new WeChatPackage($appId,$busiType,WeChatPackage::WEEK_FROM);
        $keyCode = 'REISSUE_PIC_WORD';
        list($actName, $sendName, $wishing) = CashGrantService::getRedPackConfigWord($keyCode);

        $mchBillNo = self::getMchBillNo($next);

        $openId = $next['open_id'];
        $value = $next['awardNum'] * 100;

        $resultData = $wx->sendPackage($mchBillNo, $actName, $sendName, $openId, $value, $wishing, 'redPack');

        if(!$resultData || trim($resultData['result_code']) != WeChatAwardCashDealModel::RESULT_SUCCESS_CODE) {
            throw new RunTimeException(['fail'],['nextData'=>$next,'result_code'=>$resultData['err_code'],'bill_no'=>$mchBillNo ,'step'=>WhiteGrantRecordModel::GRANT_STEP_5,'awardNum' => $next['awardNum'] * 100, 'msg' =>WeChatAwardCashDealModel::getWeChatErrorMsg($resultData['err_code'])]);
        }

        $data = [
            'msg' => '发放成功',
            'step'=>WhiteGrantRecordModel::GRANT_STEP_0,
            'bill_no' => $mchBillNo,
            'awardNum' => $next['awardNum'],
        ];

        self::create($next['student'], $data,WhiteGrantRecordModel::STATUS_GIVE);
    }

    /**
     * 获取订单ID
     * @param $next
     * @return mixed|string
     */
    public static function getMchBillNo($next)
    {
        if (!empty($next['grantInfo']) && in_array($next['grantInfo']['reason'], [WeChatAwardCashDealModel::CA_ERROR, WeChatAwardCashDealModel::SYSTEMERROR])) {
            return $next['grantInfo']['bill_no'];
        } else {
            return $_ENV['ENV_NAME'] . min($next['awardIds'] ) . $next['awardNum'] . date('Ymd');
        }
    }

    /**
     * 扣减金叶子
     * @param $next
     * @throws RunTimeException
     */
    public static function deduction($next){
        $data = [
            'uuid' => $next['student']['uuid'],
            'datatype' => ErpStudentAccountModel::DATA_TYPE_LEAF,
            'num'   => $next['awardNum'],
            'source_type' => Erp::SOURCE_TYPE_OP_TO_MONEY,
            'operator_uuid' => $next['operator_id'] ?? 0,
            'remark'    => 'OP系统扣减兑换现金',
        ];

        $res = (new Erp())->reduce_account($data);
        if(!$res){
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_3,'awardNum' => $next['awardNum'], 'msg' => '金叶子扣减失败']);
        }

        $next['awardNum'] = $next['awardNum'] / 100;
        self::getBindInfo($next);
    }

    /**
     * 创建
     * @param $student
     * @param $data
     * @param $status
     */
    public static function create($student, $data, $status){
        $now = time();
        $insert = [
            'bill_no'           => $data['bill_no'] ?? 0,
            'uuid'              => $student['uuid'] ?? '',
            'open_id'           => $data['nextData']['open_id'],
            'grant_money'       => $data['awardNum'],
            'reason'            => $data['msg'],
            'status'            => $status,
            'task_info'         => json_encode($data['taskArr'] ?? []),
            'course_manage_id'  => $student['course_manage_id'] ?? 0,
            'grant_step'        => $data['step'],
            'operator_id'       => 0,
            'grant_time'        => $status == WhiteGrantRecordModel::STATUS_GIVE ? $now : 0,
            'create_time'       => $now,
            'update_time'       => 0,
            'award_ids'         => implode(',', $data['nextData']['awardIds']),
            'result_code'       => $data['result_code'] ?? '',
            'remark'            => '',
        ];

        if(isset($data['nextData']['grantInfo']['id'])){
            $id = $data['nextData']['grantInfo']['id'];
            WhiteGrantRecordModel::updateRecord($id, $insert);
        }else{

            $res = WhiteGrantRecordModel::insertRecord($insert);
        }

    }

    /**
     * 获取绑定微信、公众号等信息
     * @param $next
     * @throws RunTimeException
     */
    public static function getBindInfo($next){

        $wechatSubscribeInfo = DssWechatOpenIdListModel::getUuidOpenIdInfo([$next['student']['uuid']]);
        $wechatSubscribeInfo = array_column($wechatSubscribeInfo, null, 'uuid');
        $subscribeStatus = $wechatSubscribeInfo[$next['student']['uuid']]['subscribe_status'];
        $bindStatus = $wechatSubscribeInfo[$next['student']['uuid']]['bind_status'];
        $openId = $wechatSubscribeInfo[$next['student']['uuid']]['open_id'];

        if($bindStatus != DssUserWeiXinModel::STATUS_NORMAL || $subscribeStatus != DssWechatOpenIdListModel::SUBSCRIBE_WE_CHAT){
            $msg = $bindStatus != DssUserWeiXinModel::STATUS_NORMAL ? '未绑定微信' : '未关注公众号';
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_4, 'awardNum' => 0, 'msg' => $msg]);
        }

        $next['open_id'] = $openId;

        //5.微信转账
        self::sendPackage($next);

    }

    /**
     * 获取awardList
     * @param array $ids
     * @return array
     */
    public static function getAwardList($ids = []){

        $s = strtotime(date('Y-m-01', strtotime('-1 month')));
        $e = strtotime('-1 day', strtotime(date('Y-m-01 23:59:59')));

        $where = [
            'status'=>ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING,
            'award_node' => 'week_award',
            "review_time[<>]" => [$s, $e],
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
