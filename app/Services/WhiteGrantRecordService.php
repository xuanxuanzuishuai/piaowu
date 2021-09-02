<?php
/**
 * 白名单发放
 * User: yangpeng
 * Date: 2021/8/13
 * Time: 10:35 AM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\NewSMS;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Libs\WeChatPackage;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Dss\DssWechatOpenIdListModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WhiteGrantRecordModel;

class WhiteGrantRecordService
{

    const LIMIT_MAX_SEND_MONEY = 9000;//红包最大发放金额(单位:分)

    public static $WeChatMiniPro;
    /**
     * 获取发放列表
     * @param $params
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function list($params, $page, $pageSize){

        list($list , $total) = WhiteGrantRecordModel::getList($params, $page, $pageSize);
        if(!$total){
            return compact('list', 'total');
        }

        //获取绑定状态
        $uuids = array_column($list, 'uuid');
        $wechatSubscribeInfo = DssWechatOpenIdListModel::getUuidOpenIdInfo($uuids);
        $wechatSubscribeInfo = array_column($wechatSubscribeInfo, null, 'uuid');

        foreach ($list as &$one){
            $one['nickname'] = Util::textDecode($one['nickname']);
            $one['reason'] = in_array($one['status'], [WhiteGrantRecordModel::STATUS_GIVE, WhiteGrantRecordModel::STATUS_GIVE_NOT_SUCC]) ? '' : $one['reason'];
            $one['current_open_id'] = $wechatSubscribeInfo[$one['uuid']]['open_id'] ?? '';
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
            'operator_id' => $params['operator_id'],
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
        $nextData['awardNum']   = $grantInfo['grant_money'];
        $nextData['operator_uuid'] = $params['operator_id'];
        $nextData['operator_id'] = $params['operator_id'];
        $nextData['awardIds'] = $grantInfo['award_ids'] ? explode(',', $grantInfo['award_ids']) : [];
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
                case WhiteGrantRecordModel::GRANT_STEP_5:
                    WhiteGrantRecordService::getBindInfo($nextData);
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
        $awardNum = 0;
        $awardIds = $next['awardIds'] ?? [];

        foreach ($next['list'] as $one){
            //修改状态为已发放
            $res = (new Erp())->addEventTaskAward($next['uuid'], $one['event_task_id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE, $one['id']);

            $awardNum += $one['award_num'];
            if ($res['code'] == Valid::CODE_SUCCESS) {
                $taskArr['succ'][] = $one['id'];
            }else{
                SimpleLogger::error('ERP_CREATE_USER_EVENT_TASK_AWARD_FAIL', [$one]);
                $taskArr['fail'][] = $one['id'];
            }

            $awardIds[] = $one['id'];
        }

        $next['awardNum'] = $next['grantInfo']['grant_money'] ?? $awardNum;
        $next['awardIds'] = $awardIds;

        //2.金叶子发放失败
        if(!empty($taskArr['fail'])){
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_2,'awardNum' => $awardNum, 'msg' => '金叶子发放失败', 'taskArr' => $taskArr]);
        }

        self::deduction($next);

    }

    /**
     * 微信发放红包
     * @param $next
     * @throws RunTimeException
     */
    public static function sendPackage($next){

        if (($_ENV['ENV_NAME'] == 'pre' && in_array($next['open_id'], explode(',', RedisDB::getConn()->get('red_pack_white_open_id')))) || $_ENV['ENV_NAME'] == 'prod') {
            $keyCode = 'REISSUE_PIC_WORD';
            list($actName, $sendName, $wishing) = CashGrantService::getRedPackConfigWord($keyCode);

            //发送红包
            $appId      = Constants::SMART_APP_ID;
            $busiType   = DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER;
            $mchBillNo  = self::getMchBillNo($next);
            $openId     = $next['open_id'];
            $value      = $next['awardNum'];
            $wx = new WeChatPackage($appId,$busiType,WeChatPackage::WEEK_FROM);
            $resultData = $wx->sendPackage($mchBillNo, $actName, $sendName, $openId, $value, $wishing, 'redPack');

            //获取用户头像
            self::$WeChatMiniPro = WeChatMiniPro::factory($appId,$busiType);
            list($nickname, $headimgurl) = WhiteGrantRecordService::getUserWxInfo($openId);

            $next['nickname']   = $nickname;
            $next['headimgurl'] = $headimgurl;
            $next['bill_no']    = $mchBillNo;
            $next['awardNum']   = $value;

            if(!$resultData || trim($resultData['result_code']) != WeChatAwardCashDealModel::RESULT_SUCCESS_CODE) {
                SimpleLogger::error('sendErr5', ['resultData'=>$resultData,'bill_no'=>$mchBillNo,'actName'=>$actName, 'sendName'=>$sendName, 'openId'=>$openId, 'value'=>$value, 'wishing'=>$wishing]);
                throw new RunTimeException(['fail'],['nextData'=>$next,'result_code'=>$resultData['err_code'],'bill_no'=>$mchBillNo ,'step'=>WhiteGrantRecordModel::GRANT_STEP_5,'awardNum' => $next['awardNum'], 'msg' =>WeChatAwardCashDealModel::getWeChatErrorMsg($resultData['err_code'])]);
            }



        }else{
            SimpleLogger::error('envErr', [$_ENV['ENV_NAME'], $next]);
            throw new RunTimeException(['fail'],['nextData'=>$next,'result_code'=>WhiteGrantRecordModel::ENVIRONMENT_NOE_EXISTS ,'step'=>WhiteGrantRecordModel::GRANT_STEP_5,'awardNum' => $next['awardNum'], 'msg' =>'环境不正确']);
        }

        self::create($next['student'], ['nextData' => $next,'msg'=>'发放成功','step'=>WhiteGrantRecordModel::GRANT_STEP_0],WhiteGrantRecordModel::STATUS_GIVE);
    }

    /**
     * 获取订单ID
     * @param $next
     * @return mixed|string
     */
    public static function getMchBillNo($next)
    {
        if (!empty($next['grantInfo']) && in_array($next['grantInfo']['result_code'], [WeChatAwardCashDealModel::CA_ERROR, WeChatAwardCashDealModel::SYSTEMERROR])) {
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
            'num'   => $next['awardNum'] * 100,
            'source_type' => Erp::SOURCE_TYPE_OP_TO_MONEY,
            'operator_uuid' => $next['operator_id'] ?? 0,
            'remark'    => 'OP系统扣减兑换现金',
        ];

        $res = (new Erp())->reduce_account($data);
        if(!$res){
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_3,'awardNum' => $next['awardNum'], 'msg' => '金叶子扣减失败']);
        }

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
            'bill_no'           => $data['nextData']['bill_no'] ?? 0,
            'uuid'              => $student['uuid'] ?? '',
            'open_id'           => $data['nextData']['open_id'] ?? '',
            'reason'            => $status == WhiteGrantRecordModel::STATUS_GIVE_FAIL ? $data['msg'] : '',
            'status'            => $status,
            'task_info'         => json_encode($data['taskArr'] ?? []),
            'course_manage_id'  => $student['course_manage_id'] ?? 0,
            'grant_step'        => $data['step'],
            'operator_id'       => $data['nextData']['operator_id'] ?? 0,
            'grant_time'        => $status == WhiteGrantRecordModel::STATUS_GIVE ? $now : 0,
            'nickname'          => $data['nextData']['nickname'] ?? '',
            'headimgurl'        => $data['nextData']['headimgurl'] ?? '',
            'update_time'       => 0,
            'award_ids'         => implode(',', $data['nextData']['awardIds'] ?? []),
            'result_code'       => $data['result_code'] ?? '',
            'remark'            => $data['nextData']['remark'] ?? 0,
        ];

        if(isset($data['nextData']['grantInfo']['id'])){
            $insert['update_time'] = $now;
            $id = $data['nextData']['grantInfo']['id'];
            WhiteGrantRecordModel::updateRecord($id, $insert);
        }else{
            $insert['grant_money'] = $data['nextData']['awardNum'] ?? 0;
            $insert['create_time'] = $now;
            WhiteGrantRecordModel::insertRecord($insert);
        }

        self::sendSms($student['mobile'], $status, $data['nextData']['awardNum']);

        //发送红包成功时发送微信消息
        if($insert['status'] == WhiteGrantRecordModel::STATUS_GIVE){
            self::pushSuccMsg($insert['open_id'], $data['nextData']['awardNum']);
        }

    }

    public static function sendSms($mobile, $status, $leaf){
        $m = date('m', strtotime('-1 month'));
        if($status == WhiteGrantRecordModel::STATUS_GIVE){
            $msg = '亲爱的用户:您在'. $m .'月份参与的限定福利活动，共计获得'. $leaf .'金叶子,专属福利已发放至您的账户,请进入【小叶子智能陪练公众号】领取红包。';
        }

        if($status == WhiteGrantRecordModel::STATUS_GIVE_FAIL){
            $msg = '亲爱的用户:您在'. $m .'月份参与的限定福利活动，共计获得'.$leaf.'金叶子,专属福利发放失败，可以联系您的课管重新发放。';
        }

        if(empty($msg) || empty($leaf)){
            SimpleLogger::error('weehWhiteSendSmsFail', [$mobile, $status, $leaf]);
            return false;
        }

        return (new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host')))->sendCommonSms($msg, $mobile);
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

        self::checkQuota($next);

    }

    /**
     * 检测是否超额发放
     * @param $next
     * @throws RunTimeException
     */
    public static function checkQuota($next){
        if(intval($next['awardNum']) > self::LIMIT_MAX_SEND_MONEY){
            throw new RunTimeException(['fail'],['nextData'=>$next, 'step'=>WhiteGrantRecordModel::GRANT_STEP_4, 'awardNum' => 0, 'msg' => '异常数据,超过单月发放上限']);
        }

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
            'award_node' => ErpUserEventTaskAwardGoldLeafModel::WEEK_WHITE_WEEK_AWARD,
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

    public static function getWeekRedPkgStatus($data){

        $now = time();
        $weChatPackage = new WeChatPackage(Constants::SMART_APP_ID, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER, WeChatPackage::WEEK_FROM);

        $resultData = $weChatPackage->getRedPackBillInfo($data['bill_no']);

        SimpleLogger::info("wx red pack query", ['mch_billno' => $data['bill_no'], 'data' => $resultData]);

        if ($resultData['result_code'] == WeChatAwardCashDealModel::RESULT_FAIL_CODE) {
            $resultCode = $resultData['err_code'];
            $status     = WhiteGrantRecordModel::STATUS_GIVE_FAIL;
            $step = WhiteGrantRecordModel::GRANT_STEP_5;
        } else {
            //已领取
            if (in_array($resultData['status'], [WeChatAwardCashDealModel::RECEIVED])) {
                $status = WhiteGrantRecordModel::STATUS_GIVE_NOT_SUCC;
                $resultCode = $resultData['status'];
                $step = WhiteGrantRecordModel::GRANT_STEP_0;
            }

            //发放失败/退款中/已退款
            if (in_array($resultData['status'], [WeChatAwardCashDealModel::REFUND, WeChatAwardCashDealModel::RFUND_ING, WeChatAwardCashDealModel::FAILED])) {
                $status = WhiteGrantRecordModel::STATUS_GIVE_FAIL;
                $resultCode = $resultData['status'];
                $step = WhiteGrantRecordModel::GRANT_STEP_5;
            }

            //发放中/已发放待领取
            if (in_array($resultData['status'], [WeChatAwardCashDealModel::SENDING, WeChatAwardCashDealModel::SENT])) {
                $status = WhiteGrantRecordModel::STATUS_GIVE;
                $resultCode = $resultData['status'];
                $step = WhiteGrantRecordModel::GRANT_STEP_0;
            }
        }

        $updateData = [
            'update_time' => $now,
            'reason'      => WeChatAwardCashDealModel::getWeChatErrorMsg($resultCode),
            'result_code' => $resultCode,
            'status'      => $status,
            'grant_step'  => $step,
        ];

        WhiteGrantRecordModel::updateRecord($data['id'], $updateData);
    }

    /**
     * 获取用户头像信息
     * @param $appId
     * @param $busiType
     * @param $openId
     * @return array|string[]
     * @throws RunTimeException
     */
    public static function getUserWxInfo($openId){
        if(is_null(self::$WeChatMiniPro)){
            self::$WeChatMiniPro = WeChatMiniPro::factory(Constants::SMART_APP_ID, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        }
        $userWxInfo = self::$WeChatMiniPro->getUserInfo($openId);
        if($userWxInfo['subscribe'] == 1){
            return [Util::textEncode($userWxInfo['nickname']), $userWxInfo['headimgurl']];
        }
        return ['', ''];
    }

    public static function pushSuccMsg($openId, $leaf, $old = false){
        if(is_null(self::$WeChatMiniPro)){
            self::$WeChatMiniPro = WeChatMiniPro::factory(Constants::SMART_APP_ID, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        }
        $obj = self::$WeChatMiniPro;
        if($old){
            $m = '2021年8月2日0点到2021年8月25日17点50分';
            $msg = '亲爱的用户:您在'.$m.'期间参与限定福利活动的专属福利已发放,请领取~';
        }else{
            $m = date('m', strtotime('-1 month'));
            $msg = '亲爱的用户:您在'.$m .'月份参与的限定福利活动，共计获得'.$leaf.'金叶子,专属福利已发放,请领取~';
        }
        $content = Util::textDecode($msg);
        $res = $obj->sendText($openId, $content);
        if($res['errcode'] != 0){
            SimpleLogger::info("whiteSendMsgFail", ['data' => $res,'openId'=>$openId,'leaf'=>$leaf,'old'=>$old]);
        }

    }
}
