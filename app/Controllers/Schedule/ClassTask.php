<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 17:57
 */

namespace App\Controllers\Schedule;


use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ClassTaskModel;
use App\Models\ClassUserModel;
use App\Models\STClassModel;
use App\Services\ClassroomService;
use App\Services\ClassTaskService;
use App\Services\ClassUserService;
use App\Services\CourseService;
use App\Services\ScheduleService;
use App\Services\ScheduleUserService;
use App\Services\STClassService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ClassTask extends ControllerBase
{
}