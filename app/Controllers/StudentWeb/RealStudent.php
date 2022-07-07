<?php
namespace App\Controllers\StudentWeb;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\Erp\ErpStudentModel;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class RealStudent extends ControllerBase
{
    /**
     * 激活例子
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activeLeads(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'uuid',
                'type'       => 'required',
                'error_code' => 'uuid_is_required'
            ],
            [
                'key'        => 'channel_id',
                'type'       => 'required',
                'error_code' => 'channel_id_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $studentData = ErpStudentModel::getRecord(['uuid' => $params['uuid']], ['id']);
            if (empty($studentData)) {
                throw new RunTimeException(['uuid_not_found']);
            }
            StudentService::studentLoginActivePushQueue(Constants::REAL_APP_ID, $studentData['id'], Constants::REAL_STUDENT_LOGIN_TYPE_REAL_LESSON_H5, $params['channel_id'] ?: 0);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson(['code' => Valid::CODE_SUCCESS], StatusCode::HTTP_OK);
    }
}
