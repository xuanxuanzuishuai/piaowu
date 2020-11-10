<?php


namespace App\Controllers\StudentApp;


use App\Controllers\OrgWeb\Collection;
use App\Libs\HttpHelper;
use App\Libs\MysqlDB;
use App\Libs\Valid;
use App\Services\StudentLeaveLogService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
class leave extends Collection
{
    /**
     * 学生取消请假
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentCancelLeave(Request $request, Response $response)
    {

        $studentId = $this->ci['student']['id'];

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $errorCode = StudentLeaveLogService::studentCancelLeave($studentId);
        if (!empty($errorCode)) {
            $db->rollBack();

            return HttpHelper::buildOrgWebErrorResponse($response, 'student_cancel_error');
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }

}