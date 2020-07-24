<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2020/07/14
 * Time: 6:14 PM
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Models\EmployeeModel;
use App\Models\StudentModel;
use App\Models\StudentWorkOrderModel;
use App\Models\StudentWorkOrderReplayModel;
use App\Models\UserWeixinModel;

class MakeOperaService
{
    //用户权限状态
    const USER_STATUS_NORMAL=0;
    const USER_STATUS_REGISTER=1;
    const USER_STATUS_TRY_TIME_END=2;
    const USER_STATUS_PAY_TIME_END=3;

    //工单状态
    const SWO_STATUS_PENDING_APPROVAL= 1;
    const SWO_STATUS_APPROVAL_FAIL= 2;
    const SWO_STATUS_APPROVAL_PASS= 3;
    const SWO_STATUS_MAKING= 4;
    const SWO_STATUS_CONFIG= 5;
    const SWO_STATUS_COMPLETE= 6;
    const SWO_STATUS_CANCEL= 7;

    //工单回复状态
    const SWO_REPLY_STATUS_PENDING= 1;
    const SWO_REPLY_STATUS_COMPLETE= 3;

    //工单节点
    const IS_NOT_CUR= 0;
    const IS_CUR= 1;

    //申请平台
    const APPLY_FROM_WEIXIN= 1;
    const APPLY_FROM_WEB= 2;

    const STATUS_NORMAL = 1;
    const STATUS_DEL = 0;

    /**
     * @param $studentId
     * @return array|mixed
     * 获取用户打谱权限和最近一个订单的进度
     */
    public static function getStudentAndSwoInfo($studentId)
    {
        $studentAndSwoInfo = StudentModel::getStudentAndSwoById($studentId)[0];
        if (empty($studentAndSwoInfo)){
            return [];
        }
        $ret = [
            'user_id' => $studentId,
            'user_mobile' => $studentAndSwoInfo['mobile'],
            'user_status' => self::USER_STATUS_NORMAL,
            'apply_permission' => false,
            'swo' => [],

        ];
        if (empty($studentAndSwoInfo['swo_id'])){
            $ret = self::firstCheck($ret,$studentAndSwoInfo);
        }else{
           $ret = self::scheduleCheck($ret,$studentAndSwoInfo);
        }
        return $ret;

    }

    /**
     * @param $ret
     * @param $studentAndSwoInfo
     * @return mixed
     * 检查用户首次打谱权限
     */
    public static function firstCheck($ret,$studentAndSwoInfo)
    {
        switch ($studentAndSwoInfo['has_review_course']){
            case 0:
                $ret['user_status'] = self::USER_STATUS_REGISTER;
                break;
            case 1:
                if (!StudentServiceForApp::getSubStatus($studentAndSwoInfo['user_id'])){
                    $ret['user_status'] = self::USER_STATUS_TRY_TIME_END;
                }else{
                    $ret['apply_permission'] = true;
                }
                break;
            case 2:
                if (!StudentServiceForApp::getSubStatus($studentAndSwoInfo['user_id'])){
                    $ret['user_status'] = self::USER_STATUS_PAY_TIME_END;
                }else{
                    $ret['apply_permission'] = true;
                }
                break;
        }
        return $ret;
    }

    /**
     * @param $ret
     * @param $studentAndSwoInfo
     * @return mixed
     * 检查用户再次打谱权限
     */
    public static function scheduleCheck($ret,$studentAndSwoInfo)
    {
        //工单最后更新时间
        $time = date("Y-m-d",strtotime($studentAndSwoInfo['update_time']));
        $ret['swo'] = [
            "swo_id" => $studentAndSwoInfo['swo_id'],
            "swo_status" =>$studentAndSwoInfo['swo_status'],
            "refuse_msg" =>$studentAndSwoInfo['refuse_msg'],
            "view_guidance" =>$studentAndSwoInfo['view_guidance'],
            "estimate_day" => date("Y年m月d日",strtotime($studentAndSwoInfo['estimate_day'])),
            "next_apply_time" => '',
        ];

        switch ($studentAndSwoInfo['has_review_course']){
            case 0:
                $ret['user_status'] = self::USER_STATUS_REGISTER;
                break;
            case 1:
                if ($studentAndSwoInfo['swo_status'] == self::SWO_STATUS_COMPLETE){
                    $ret['user_status'] = self::USER_STATUS_NORMAL;
                }
                break;
            case 2:
                if ($studentAndSwoInfo['swo_status'] == self::SWO_STATUS_COMPLETE){
                    if (!StudentServiceForApp::getSubStatus($studentAndSwoInfo['user_id'])){
                        $ret['user_status'] = self::USER_STATUS_PAY_TIME_END;
                    }
                    if (time()<= strtotime("$time+8 day")){
                        $ret['user_status'] = self::USER_STATUS_NORMAL;
                        $ret['swo']['next_apply_time']= date("Y年m月d日",strtotime("$time+8 day"));
                    }else{
                        $ret['apply_permission'] = true;
                    }
                }
                break;
        }
        return $ret;
    }

    /**
     * @param $params
     * @return array|int|mixed|string
     * @throws RunTimeException
     * 生成工单记录
     * 微信服务端最多可以传5张照片，后台最多可以传10张
     */
    public static function getSwoId($params)
    {
        $operaImgNum = count($params['opera_images']['opera_imgs']);

        if ($params['creator_type']==self::APPLY_FROM_WEIXIN && $operaImgNum>5){
            throw new RunTimeException(['The number of pictures exceeds the limit.']);
        }else if($params['creator_type']==self::APPLY_FROM_WEB && $operaImgNum>10){
            throw new RunTimeException(['The number of pictures exceeds the limit.']);
        }

        $creatorName = self::getCreatorName($params)??'';
        $time = date("Y-m-d H:i:s",time());
        $insertData = [
            'student_id' => $params['student_id'],
            'student_opera_name' => $params['opera_name'],
            'attachment' => serialize($params['opera_images']),
            'opera_num' => $operaImgNum,
            'creator_id' => $params['creator_id'],
            'creator_name' => $creatorName,
            'creator_type' => $params['creator_type'],
            'updator_id' => $params['creator_id'],
            'update_time' => $time,
            'estimate_day' => $time,
        ];
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $swoId = StudentWorkOrderModel::insertRecord($insertData, false);
        if (empty($swoId)){
            $db->rollBack();
            throw new RunTimeException(['add_swo_failed']);
        }
        $insertSwoReplyData = [
            [
                'swo_id'=>$swoId,
                'swo_status'=>self::SWO_STATUS_PENDING_APPROVAL,
                'status'=>self::SWO_REPLY_STATUS_PENDING,
                'is_cur'=>self::IS_CUR,
                'creator_id'=>$params['creator_id'],
                'create_time'=> $time,
                'reply_id'=>-1,
                'reply_time'=>$time,
            ],
            [
                'swo_id'=>$swoId,
                'swo_status'=>self::SWO_STATUS_APPROVAL_PASS,
                'status'=>self::SWO_REPLY_STATUS_PENDING,
                'is_cur'=>self::IS_NOT_CUR,
                'creator_id'=>$params['creator_id'],
                'create_time'=> $time,
                'reply_id'=>-1,
                'reply_time'=> $time,
            ],
        ];
        $swoReplyId = StudentWorkOrderReplayModel::insertRecord($insertSwoReplyData,false);
        if (empty($swoReplyId)){
            $db->rollBack();
            throw new RunTimeException(['add_swo_reply_failed']);
        }
        $db->commit();
        return $swoId;
    }

    public static function getCreatorName($params){
        //获取创建人信息
        if ($params['creator_type']==1){
            //创建人来自微信，创建人和学员是同一人
            $where = ['id' => $params['creator_id'], 'status' =>1];
            $creatorInfo = StudentModel::getSingleStudentInfo($where,['name']);
            if (!isset($creatorInfo)){
                return '';
            }
            $creatorName =$creatorInfo['name'];
        }else{
            //创建人来自dss,可能是助教或者课管
            $creatorInfo = EmployeeModel::getEmployeeWithIds($params['creator_id']);
            if (!isset($creatorInfo[0])){
                return '';
            }
            $creatorName =$creatorInfo[0]['name'];
        }
        return $creatorName;
    }

    /**
     * @param $params
     * @return array
     * @throws RunTimeException
     * 根据工单号更新工单为撤销状态
     */
    public static function cancelSwo($params)
    {
        $swoInfo  = StudentWorkOrderModel::getSwoById($params['swo_id'],['id','status']);

        $updateSwoReplyWhere = [
            'swo_status' => self::SWO_STATUS_APPROVAL_PASS,
            'swo_id'=>$params['swo_id']
        ];
        $updateSwoReplyData = [
            'status' => self::SWO_REPLY_STATUS_COMPLETE,
            'is_cur' => self::IS_NOT_CUR,
            'reply_id' => $params['user_id'],
            'reply_time'=>date("Y-m-d H:i:s")
        ];
        $insertCancelNode = [
            'swo_id'=>$params['swo_id'],
            'swo_status'=>self::SWO_STATUS_CANCEL,
            'status'=>self::SWO_REPLY_STATUS_COMPLETE,
            'is_cur'=>self::IS_CUR,
            'creator_id'=>$params['user_id'],
            'create_time'=>date("Y-m-d H:i:s"),
            'reply_id'=>-1,
            'reply_time'=>date("Y-m-d H:i:s"),
        ];
        //订单只有在开始制作之前才可以撤销
        if ($swoInfo['status']==self::SWO_REPLY_STATUS_PENDING || $swoInfo['status']==self::SWO_STATUS_APPROVAL_PASS){
            $db = MysqlDB::getDB();
            try {
                $db->beginTransaction();
                $cancelRes  = StudentWorkOrderModel::UpdateSwoById($params['swo_id'],['status'=>self::SWO_STATUS_CANCEL]);
                StudentWorkOrderReplayModel::updateData($updateSwoReplyWhere,$updateSwoReplyData);
                StudentWorkOrderReplayModel::insertRecord($insertCancelNode,false);
                $db->commit();
            }catch (\Exception $e){
                $db->rollBack();
                throw new RunTimeException(['cancel_swo_fail']);
            }
        }
        return [
            'swo_id'=>$params['swo_id'],
            'cancel_res'=>$cancelRes??false
        ];
    }

    /**
     * @param $studentId
     * @param int $page
     * @param int $limit
     * @return array
     * 查询用户打谱申请历史记录
     */
    public static function getHistoryList($studentId,$page = 1, $limit = 10)
    {
        $where = [
            "student_id"=> $studentId,
            "ORDER" => ["create_time" => "DESC"],
            "LIMIT" => [($page-1)*$limit,$limit]
        ];
        $files = [
            'id(swo_id)',
            'status',
            'create_time(apply_time)',
            'student_opera_name(open_name)'
        ];
        $swoList  = StudentWorkOrderModel::getRecords($where,$files)??[];
        $total = StudentWorkOrderModel::getTotalNum($where)??0;
        return [$swoList,$total];
    }

    /**
     * @param $swoId
     * @return array|mixed
     * 查询曲谱详情
     */
    public static function getOperaInfo($swoId)
    {
        $operaInfo = StudentWorkOrderModel::getSwoById($swoId,['id(swo_id)','student_opera_name(opera_name)','attachment(opera_images)']);
        if (empty($operaInfo)){
            return [];
        }
        $operaInfo['opera_images'] = unserialize($operaInfo['opera_images']);
        //获取图片真实链接
        foreach ($operaInfo['opera_images'] as $key => $value){
            if (is_array($value)){
                foreach ($value as $k => $v){
                    $operaInfo['opera_images']['opera_imgs'][$k] = AliOSS::signUrls($v);
                }
            }else{
                $operaInfo['opera_images'][$key] = AliOSS::signUrls($value);
            }
        }
        return $operaInfo;
    }

    /**
     * @param $params
     * @return array
     * 获取用户信息
     */
    public static function getStudentInfo($params)
    {
        $where = [
            "mobile"=> $params['mobile'],
        ];
        $files = [
            'id',
            'name',
        ];
        $swoList  = StudentModel::getSingleStudentInfo($where,$files);
        if (empty($swoList)){
            return [];
        }
        return [$swoList];
    }

    /**
     * @param $params
     * @return array
     * 获取工单列表
     */
    public static function getSwoList($params)
    {
        $swoMap = [
            1=>'待审核',
            2=>'未通过',
            3=>'已通过',
            4=>'制作中',
            5=>'配置中',
            6=>'已完成',
            7=>'已撤销',
        ];
        $studentStatusMap = [
            0=>"已注册",
            1=>'付费体验课',
            2=>'付费用户'
        ];
        $params['page'] = $params['page']??1;
        $params['limit'] = $params['limit']??10;
        $params['apply_time_sort'] = $params['apply_time_sort']?:"DESC";
        list($list,$totalNum)=StudentWorkOrderModel::getSwoDetailList($params);
        foreach ($list as &$value){
            $value['status_value'] = $swoMap[$value['status']];
            $value['has_review_course'] = $studentStatusMap[$value['has_review_course']];
        }
        return [$list,$totalNum];
    }

    /**
     * @param $employeeId
     * @return array
     * 整理助教，课管，制作人和配置人信息
     * 登录角色为助教或者课管时，只返回对应角色当前登录用户信息
     */
    public static function getMakerConfigList($employee)
    {
        $assistantRoleId = DictConstants::get(DictConstants::ORG_WEB_CONFIG,'assistant_role');
        $courseManagerRoleId = DictConstants::get(DictConstants::ORG_WEB_CONFIG,'course_manage_role');
        $makerRoleId = DictConstants::get(DictConstants::ORG_WEB_CONFIG,'maker_role');
        $configRoleId = DictConstants::get(DictConstants::ORG_WEB_CONFIG,'config_role');

        $roleIdList = [
            $assistantRoleId,
            $courseManagerRoleId,
            $makerRoleId,
            $configRoleId
        ];
        $where = [
            'role_id' => $roleIdList,
            'status' => self::STATUS_NORMAL
        ];
        $makerConfigList = EmployeeModel::getRecords($where,['id', 'name','role_id'],false);
        if (empty($makerConfigList)){
            return [];
        }
        foreach ($makerConfigList as $value){
            switch ($value['role_id']){
                case $assistantRoleId:
                    $data['assistantList'][]=$value;
                    break;
                case $courseManagerRoleId:
                    $data['managerList'][]=$value;
                    break;
                case $makerRoleId:
                    $data['makerList'][]=$value;
                    break;
                case $configRoleId:
                    $data['configList'][]=$value;
                    break;
            }
            if ($value['id']==$employee['id']){
                $temRole = $value;
            }
        }

        if (isset($temRole) && !empty($temRole)){
            if ($employee['role_id']==$makerRoleId){
                $data['makerList']=$temRole;
            }elseif ($employee['role_id']==$configRoleId){
                $data['configList']=$temRole;
            }
        }
        return $data??[];
    }

    /**
     * @param $params
     * @return array|int
     * 更新工单制作人或配置人信息，并插入或更新流转记录
     */
    public static function distributeTask($params)
    {
        //判断工单制作人或配置人是否可以更新
        if ($params['type']==1){
            $distributeType=[self::SWO_STATUS_PENDING_APPROVAL,self::SWO_STATUS_APPROVAL_PASS];
            $data = ['opera_maker_id'=>$params['target_id']];
        }else{
            $distributeType=[self::SWO_STATUS_PENDING_APPROVAL,self::SWO_STATUS_APPROVAL_PASS,self::SWO_STATUS_MAKING,self::SWO_STATUS_CONFIG];
            $data = ['opera_config_id'=>$params['target_id']];
        }
        $where = [
            'id'=>$params['swo_ids'],
            'status'=> $distributeType
        ];

        //更新工单制作人和配置人信息
        return StudentWorkOrderModel::batchUpdateRecord($data,$where,false)?:0;
    }

    /**
     * @param $params
     * @return bool|string
     * @throws RunTimeException
     * 工单审核逻辑
     */
    public static function swoApprove($params)
    {
        //工单如果不是待审核状态，直接返回请求
        $swoInfo = StudentWorkOrderModel::getRecord(['id'=>$params['swo_id']],['status']);
        if (empty($swoInfo) || $swoInfo['status']!= self::SWO_STATUS_PENDING_APPROVAL){
            return '';
        }

        if ($params['type']==1) {
            //审核通过逻辑
            if (empty($params['estimate_day'])){
                return '';
            }
            return self::swoApproveSuccess($params);
        }else{
            //审核拒绝逻辑
            if (empty($params['refuse_msg'])){
                return '';
            }
            return self::swoApproveFail($params);
        }
    }

    /**
     * @param $params
     * @return bool
     * @throws RunTimeException
     * 审核通过逻辑
     */
    public static function swoApproveSuccess($params)
    {
        $estimate_day = $params['estimate_day']+1;
        $updateSwoData = [
            'updator_id' => $params['user_id'],
            'update_time' => date("Y-m-d H:i:s"),
            'estimate_day'=>date("Y-m-d H:i:s",strtotime("+$estimate_day day")),
            'status' => self::SWO_STATUS_APPROVAL_PASS,
        ];
        $updateSwoReplyWhere = [
            'swo_status' => self::SWO_STATUS_PENDING_APPROVAL,
            'swo_id'=>$params['swo_id']
        ];
        $updateSwoReplyData = [
            'status' => self::SWO_REPLY_STATUS_COMPLETE,
            'is_cur' => self::IS_NOT_CUR,
            'reply_id' => $params['user_id'],
            'reply_time'=>date("Y-m-d H:i:s")
        ];
        $updateSwoReplyNextWhere = [
            'swo_status' => self::SWO_STATUS_APPROVAL_PASS,
            'swo_id'=>$params['swo_id']
        ];
        $updateSwoReplyNextData = [
            'is_cur' => self::IS_CUR,
        ];
        $db = MysqlDB::getDB();
        try {
            $db->beginTransaction();
            StudentWorkOrderModel::UpdateSwoById($params['swo_id'],$updateSwoData);
            StudentWorkOrderReplayModel::updateData($updateSwoReplyWhere,$updateSwoReplyData);
            StudentWorkOrderReplayModel::updateData($updateSwoReplyNextWhere,$updateSwoReplyNextData);
            $db->commit();
        }catch (\Exception $e){
            $db->rollBack();
            throw new RunTimeException(['approval_swo_fail']);
        }
        self::pushMakingSchedule($params['swo_id']);
        return true;
    }

    /**
     * @param $params
     * @return bool
     * @throws RunTimeException
     * 审核拒绝逻辑
     */
    public static function swoApproveFail($params)
    {
        $updateSwoData = [
            'updator_id' => $params['user_id'],
            'update_time' => date("Y-m-d H:i:s"),
            'refuse_msg'=>$params['refuse_msg'],
            'status' => self::SWO_STATUS_APPROVAL_FAIL
        ];
        $updateSwoReplyWhere = [
            'swo_status' => self::SWO_STATUS_PENDING_APPROVAL,
            'swo_id'=>$params['swo_id']
        ];
        $updateSwoReplyData = [
            'status' => self::SWO_REPLY_STATUS_COMPLETE,
            'is_cur' => self::IS_NOT_CUR,
            'reply_id' => $params['user_id'],
            'reply_time'=>date("Y-m-d H:i:s")
        ];
        $insertFailNode = [
            'swo_id'=>$params['swo_id'],
            'swo_status'=>self::SWO_STATUS_APPROVAL_FAIL,
            'status'=>self::SWO_REPLY_STATUS_COMPLETE,
            'is_cur'=>self::IS_CUR,
            'creator_id'=>$params['user_id'],
            'create_time'=>date("Y-m-d H:i:s"),
            'reply_id'=>-1,
            'reply_time'=>date("Y-m-d H:i:s"),
        ];

        $db = MysqlDB::getDB();
        try {
            $db->beginTransaction();
            StudentWorkOrderModel::UpdateSwoById($params['swo_id'],$updateSwoData);
            StudentWorkOrderReplayModel::updateRecord($updateSwoReplyWhere,$updateSwoReplyData);
            StudentWorkOrderReplayModel::insertRecord($insertFailNode,false);
            $db->commit();
        }catch (\Exception $e){
            $db->rollBack();
            throw new RunTimeException(['approval_swo_fail']);
        }
        self::pushMakingSchedule($params['swo_id']);
        return true;
    }

    /**
     * @param $params
     * @return int|mixed|string|null
     * 插入后续处理节点信息
     */
    public static function insertProcessNode($params)
    {
        $insertData = [
            [
                'swo_id'=>$params['swo_id'],
                'swo_status'=>self::SWO_STATUS_MAKING,
                'status'=>self::SWO_REPLY_STATUS_PENDING,
                'is_cur'=>self::IS_CUR,
                'creator_id'=>$params['user_id'],
                'create_time'=>date("Y-m-d H:i:s"),
                'reply_id'=>-1,
                'reply_time'=>date("Y-m-d H:i:s"),
            ],
            [
                'swo_id'=>$params['swo_id'],
                'swo_status'=>self::SWO_STATUS_CONFIG,
                'status'=>self::SWO_REPLY_STATUS_PENDING,
                'is_cur'=>self::IS_NOT_CUR,
                'creator_id'=>$params['user_id'],
                'create_time'=>date("Y-m-d H:i:s"),
                'reply_id'=>-1,
                'reply_time'=>date("Y-m-d H:i:s"),
            ]
        ];
        return StudentWorkOrderReplayModel::insertRecord($insertData,false);
    }

    /**
     * @param $params
     * @return bool
     * @throws RunTimeException
     * 开始打谱更新工单表和工单回复表
     */
    public static function start($params)
    {
        //工单如果不是已通过状态，直接返回请求
        $swoInfo = StudentWorkOrderModel::getRecord(['id'=>$params['swo_id']],['status','opera_maker_id']);
        if (empty($swoInfo) || $swoInfo['status']!= self::SWO_STATUS_APPROVAL_PASS || $swoInfo['opera_maker_id']!= $params['user_id']){
            throw new RunTimeException(['工单状态不允许或用户权限受限!']);
        }

        $updateSwoData = [
            'updator_id' => $params['user_id'],
            'update_time' => date("Y-m-d H:i:s"),
            'status' => self::SWO_STATUS_MAKING
        ];
        $updateSwoReplyWhere = [
            'swo_status' => self::SWO_STATUS_APPROVAL_PASS,
            'swo_id'=>$params['swo_id']
        ];
        $updateSwoReplyData = [
            'status' => self::SWO_REPLY_STATUS_COMPLETE,
            'is_cur' => self::IS_NOT_CUR,
            'reply_id' => $params['user_id'],
            'reply_time'=>date("Y-m-d H:i:s")
        ];
        $db = MysqlDB::getDB();
        try {
            $db->beginTransaction();
            StudentWorkOrderModel::UpdateSwoById($params['swo_id'],$updateSwoData);
            StudentWorkOrderReplayModel::updateData($updateSwoReplyWhere,$updateSwoReplyData);
            self::insertProcessNode($params);
            $db->commit();
        }catch (\Exception $e){
            $db->rollBack();
            throw new RunTimeException(['make_start_swo_fail']);
        }
        self::pushMakingSchedule($params['swo_id']);
        return true;
    }

    /**
     * @param $params
     * @return bool
     * @throws RunTimeException
     * 曲谱制作完成
     */
    public static function complete($params)
    {
        //工单如果不是制作中状态，直接返回请求
        $swoInfo = StudentWorkOrderModel::getRecord(['id'=>$params['swo_id']],['status','opera_maker_id']);
        if (empty($swoInfo) || $swoInfo['status']!= self::SWO_STATUS_MAKING || $swoInfo['opera_maker_id']!= $params['user_id']){
            throw new RunTimeException(['工单状态不允许或用户权限受限!']);
        }
        $updateSwoData = [
            'updator_id' => $params['user_id'],
            'update_time' => date("Y-m-d H:i:s"),
            'status' => self::SWO_STATUS_CONFIG,
            'textbook_name'=>$params['text_name'],
            'opera_name'=>$params['opera_name'],
            'opera_config_id' =>$params['opera_config_id']
        ];
        $updateSwoReplyWhere = [
            'swo_status' => self::SWO_STATUS_MAKING,
            'swo_id'=>$params['swo_id']
        ];
        $updateSwoReplyData = [
            'status' => self::SWO_REPLY_STATUS_COMPLETE,
            'is_cur' => self::IS_NOT_CUR,
            'reply_id' => $params['user_id'],
            'reply_time'=>date("Y-m-d H:i:s")
        ];
        $updateSwoReplyNextWhere = [
            'swo_status' => self::SWO_STATUS_CONFIG,
            'swo_id'=>$params['swo_id']
        ];
        $updateSwoReplyNextData = [
            'is_cur' => self::IS_CUR,
        ];

        $db = MysqlDB::getDB();
        try {
            $db->beginTransaction();
            StudentWorkOrderModel::UpdateSwoById($params['swo_id'],$updateSwoData);
            StudentWorkOrderReplayModel::updateData($updateSwoReplyWhere,$updateSwoReplyData);
            StudentWorkOrderReplayModel::updateData($updateSwoReplyNextWhere,$updateSwoReplyNextData);
            $db->commit();
        }catch (\Exception $e){
            $db->rollBack();
            throw new RunTimeException(['complete_swo_fail']);
        }
        return true;
    }

    /**
     * @param $params
     * @return bool
     * @throws RunTimeException
     * 启用曲谱
     */
    public static function useStart($params)
    {
        //工单如果配置中状态，直接返回请求
        $swoInfo = StudentWorkOrderModel::getRecord(['id'=>$params['swo_id']],['status','opera_config_id']);
        if (empty($swoInfo) || $swoInfo['status']!= self::SWO_STATUS_CONFIG || $swoInfo['opera_config_id']!= $params['user_id']){
            throw new RunTimeException(['工单状态不允许或用户权限受限']);
        }
        $updateSwoData = [
            'updator_id' => $params['user_id'],
            'update_time' => date("Y-m-d H:i:s"),
            'status' => self::SWO_STATUS_COMPLETE,
            'view_guidance' =>$params['view_guidance']
        ];
        $updateSwoReplyWhere = [
            'swo_status' => self::SWO_STATUS_CONFIG,
            'swo_id'=>$params['swo_id']
        ];
        $updateSwoReplyData = [
            'status' => self::SWO_REPLY_STATUS_COMPLETE,
            'is_cur' => self::IS_CUR,
            'reply_id' => $params['user_id'],
            'reply_time'=>date("Y-m-d H:i:s")
        ];
        $db = MysqlDB::getDB();
        try {
            $db->beginTransaction();
            StudentWorkOrderModel::UpdateSwoById($params['swo_id'],$updateSwoData);
            StudentWorkOrderReplayModel::updateData($updateSwoReplyWhere,$updateSwoReplyData);
            $db->commit();
        }catch (\Exception $e){
            $db->rollBack();
            throw new RunTimeException(['complete_swo_fail']);
        }
        self::pushMakingSchedule($params['swo_id']);
        return true;
    }

    /**
     * @param $swoId
     * @return bool
     * 推送曲谱制作进度
     */
    public static function pushMakingSchedule($swoId)
    {
        $swoMap = [
            1=>'待审核',
            2=>'未通过',
            3=>'已通过',
            4=>'制作中',
            5=>'配置中',
            6=>'已完成',
            7=>'已撤销',
        ];
        $swoInfo = StudentWorkOrderModel::getRecord(['id'=>$swoId],['student_id','status','student_opera_name'],false);
        if (empty($swoInfo) || !isset($swoMap[$swoInfo['status']])){
            return false;
        }
        //获取用户的openID
        $where=[
            'user_id'=>$swoInfo['student_id'],
            'user_type'=>UserWeixinModel::USER_TYPE_STUDENT,
            'status'=>1,
            'app_id'=>UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT
        ];
        $studentWxInfo = UserWeixinModel::getRecord($where,['open_id'],false);
        if (empty($studentWxInfo)){
            return false;
        }

        $pushData = [
            'first'=>[
                'value'=>'您的曲谱制作申请已处理，详情如下：',
                'color'=>'#FF8A00',
            ],
            'keyword1'=>[
                'value'=>'曲谱制作申请',
                'color'=>'#FF8A00',
            ],
            'keyword2'=>[
                'value'=>$swoInfo['student_opera_name'],
                'color'=>'#FF8A00',
            ],
            'keyword3'=>[
                'value'=>$swoMap[$swoInfo['status']],
                'color'=>'#FF8A00',
            ],
            'remark'=>[
                'value'=>'点此消息，查看详情',
                'color'=>'#FF8A00',
            ]
        ];
        $url = DictConstants::get(DictConstants::MAKE_OPERA_TEMPLATE,'status_url');
        $templateId = DictConstants::get(DictConstants::MAKE_OPERA_TEMPLATE,'template_id');
        WeChatService::notifyUserWeixinTemplateInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT, $studentWxInfo['open_id'], $templateId, $pushData, $url);
        return true;
    }
}
