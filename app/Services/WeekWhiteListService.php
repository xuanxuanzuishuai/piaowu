<?php
/**
 * 白名单列表
 * User: yangpeng
 * Date: 2021/8/12
 * Time: 10:35 AM
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\WeekWhiteListModel;
use App\Models\WhiteRecordModel;

class WeekWhiteListService
{
    /**
     * 添加白名单
     * @param $uuids
     * @param $operator_id
     * @return array[]|bool
     */
    public static function create($uuids, $operator_id)
    {
        $errData = [
            'first_pay_time_err_list' => [],    // 首次付费时间不正确的uuid列表
        ];
        //检测是否重复
        $uniqueIds            = array_unique($uuids);
        $repeatIds            = array_values(array_diff_assoc($uuids, $uniqueIds));
        $errData['repeatIds'] = $repeatIds;

        //检测是否存在
        $studentList = DssStudentModel::getUuids($uniqueIds);
        $uuidList    = $studentIdList = [];
        if (!empty($studentList)) {
            foreach ($studentList as $item) {
                $uuidList[$item['uuid']] = $item;
                $studentIdList[]         = $item['id'];
            }
            unset($item);
        }
        $diff                  = array_diff($uniqueIds, array_keys($uuidList));
        $errData['not_exists'] = array_values($diff);

        // 获取用户首次付费时间
        $userFirstPayTimeList = (new Dss())->getStudentFirstPayTime($studentIdList);
        $lastFirstPayTime = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'white_list_last_first_pay_time');
        foreach ($userFirstPayTimeList as $item) {
            if ($item['first_pay_time'] >= $lastFirstPayTime) {
                $errData['first_pay_time_err_list'][] =$item['uuid'];
            }
        }
        unset($item);

        //检测是否添加过
        $exists = WeekWhiteListModel::getRecords(['uuid' => $uniqueIds, 'status' => WeekWhiteListModel::NORMAL_STATUS], 'uuid');
        $errData['exists'] = $exists;

        // 如果有任意错误的uuid ，直接返回
        if ($errData['repeatIds'] || $errData['not_exists'] || $errData['exists'] || !empty($errData['first_pay_time_err_list'])) {
            return ['errorList' => $errData];
        }
        $insert  = [];
        $logData = [];
        $now     = time();
        foreach ($uniqueIds as $uuid) {
            $row      = [
                'student_id'  => $uuidList[$uuid]['id'],
                'uuid'        => $uuid,
                'operator_id' => $operator_id,
                'create_time' => $now,
                'status'      => WeekWhiteListModel::NORMAL_STATUS
            ];
            $insert[] = $row;
            $log       = [
                'uuid'        => $uuid,
                'operator_id' => $operator_id,
                'type'        => WhiteRecordModel::TYPE_ADD,
                'create_time' => $now,
            ];
            $logData[] = $log;
        }
        try {
            $db = MysqlDB::getDB();
            $db->beginTransaction();
            $insertWhite = WeekWhiteListModel::batchInsert($insert);
            if (!$insertWhite) {
                throw new RunTimeException(['insert_failure'], $insert);
            }
            $insertRecord = WhiteRecordService::BatchCreate($logData);
            if (!$insertRecord) {
                throw new RunTimeException(['insert_failure'], $logData);
            }
            $db->commit();
            return true;
        } catch (RunTimeException $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * 获取白名单
     * @param $params
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function getWhiteList($params, $page, $pageSize){

        $where = [
            'status' => WeekWhiteListModel::NORMAL_STATUS
        ];

        if(!empty($params['uuid'])){
            $where['uuid'] = $params['uuid'];
        }

        if(!empty($params['mobile'])){
            $studentInfo = DssStudentModel::getRecord(['mobile'=>$params['mobile']],['id','uuid','mobile']);
            $where['student_id'] = $studentInfo['id'] ?? 0;
        }

        $total = WeekWhiteListModel::getCount($where);

        if ($total <= 0) {
            return ['list'=>[], 'total'=>0];
        }

        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];
        $where['ORDER'] = ['id'=>'DESC'];
        $list = WeekWhiteListModel::getRecords($where);

        $list = self::initList($list);

        return compact('list', 'total');
    }

    public static function initList($list){

        $uuids = array_column($list, 'uuid');
        $students = DssStudentModel::getUuids($uuids);

        $students = array_column($students, null, 'uuid');
        $course_manage_ids = array_column($students, 'course_manage_id');
        $operator_ids = array_column($list, 'operator_id');
        $operator_ids = array_unique(array_merge($course_manage_ids, $operator_ids));
        $employees = DssEmployeeModel::getRecords(['id'=>$operator_ids],['id','name']);
        $employees = array_column($employees, null, 'id');


        foreach ($list as &$one){
            $one['mobile']          = Util::hideUserMobile($students[$one['uuid']]['mobile'] ?? '');
            $one['operator_name']   = $employees[$one['operator_id']]['name'] ?? '系统';
            $one['student_id']      = $students[$one['uuid']]['id'];
            $one['course_manage_name'] = $employees[$one['course_manage_id']]['name'] ?? '';

            if(isset($one['type'])){
                $one['type_text'] = WhiteRecordModel::$types[$one['type']];
            }

            if(isset($one['grant_money'])){
                $one['grant_money'] = $one['grant_money'] / 100;
            }
        }

        return $list;
    }

    /**
     * 删除白名单
     * @param $id
     * @param $operator_id
     * @return array|void
     */
    public static function del($id, $operator_id){
        $info = WeekWhiteListModel::getById($id);

        if(empty($info)){
            return false;
        }

        try {

            $db = MysqlDB::getDB();
            $db->beginTransaction();

            $edit = WeekWhiteListModel::updateRecord($id, ['status' => WeekWhiteListModel::DISABLE_STATUS, 'operator_id'=>$operator_id]);

            if(!$edit){
                throw new RunTimeException(['update_failure']);
            }

            $insert = WhiteRecordService::createOne($info['uuid'], WhiteRecordModel::TYPE_DEL, $operator_id);

            if(!$insert){
                throw new RunTimeException(['insert_failure']);
            }

            $db->commit();
            return true;
        }catch (RunTimeException $e){
            $db->rollBack();
            return false;
        }
    }

    /**
     * 检查学生是否在白名单
     * @param $openId
     * @param $msgPushRuleId
     * @return bool
     * @throws RunTimeException
     */
    public static function checkStudentIsWhite($openId, $msgPushRuleId)
    {
        if (empty($openId) && empty($msgPushRuleId)) {
            SimpleLogger::info('checkStudentIsWhite', ['msg' => 'open_id_and_rule_id_is_empty', $openId]);
            throw new RunTimeException(['record_not_found']);
        }
        // 查询哪些规则不需要过滤白名单
        $dictRuleIds = self::getNoCheckWeekWhiteMsgId();
        if (!empty($dictRuleIds) && in_array($msgPushRuleId, $dictRuleIds)) {
            return false;
        }
        $studentWeiXinInfo = DssUserWeiXinModel::getByOpenId($openId);
        $studentId = $studentWeiXinInfo['user_id'] ?? 0;
        if (empty($studentId)) {
            SimpleLogger::info('checkStudentIsWhite', ['msg' => 'student_id_is_empty', $studentId, $openId]);
            throw new RunTimeException(['unknown_user']);
        }
        $isExistsWeekWhiteList = WeekWhiteListModel::getListByStudentId($studentId);
        if (!empty($isExistsWeekWhiteList)) {
            SimpleLogger::info('send_verify_fail', ['msg' => 'is_white', $isExistsWeekWhiteList, $studentId, $openId, $studentWeiXinInfo ?? []]);
            return true;
        }
        return false;
    }

    /**
     * 查询不需要过滤周周领奖白名单用户的推送消息id
     * @return array
     */
    public static function getNoCheckWeekWhiteMsgId()
    {
        // 查询哪些规则不需要过滤白名单
        $dictRuleConfig = DictConstants::get(DictConstants::MESSAGE_RULE, 'no_check_week_white_msg_rule');
        $dictRuleIdArr = DictConstants::getValues(DictConstants::MESSAGE_RULE, explode(',', $dictRuleConfig));
        $dictRuleIds = [];
        foreach ($dictRuleIdArr as $item) {
            if (is_numeric($item)) {
                $dictRuleIds[] = intval($item);
            } else {
                $dictRuleIds = array_merge($dictRuleIds, json_decode($item, true));
            }
        }
        unset($item);
        return $dictRuleIds;
    }
}
