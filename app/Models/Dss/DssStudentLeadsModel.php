<?php
namespace App\Models\Dss;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Util;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;

class DssStudentLeadsModel extends DssModel
{
    public static $table = 'student_leads';
}