<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssErpPackageGoodsV1Model;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssGoodsV1Model;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\Erp\ErpUserEventTaskModel;

class StudentInviteModel extends Model
{
    const REFEREE_TYPE_STUDENT = 1; // 推荐人类型：学生
    const REFEREE_TYPE_AGENT = 4; // 推荐人类型：代理商
    public static $table = "student_invite";
}
