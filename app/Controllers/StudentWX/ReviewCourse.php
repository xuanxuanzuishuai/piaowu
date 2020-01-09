<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/12/25
 * Time: 4:19 PM
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Services\ReviewCourseService;
use Slim\Http\Request;
use Slim\Http\Response;

class ReviewCourse extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function getReview(Request $request, Response $response)
    {
        $params = $request->getParams();
        $studentId = $this->ci['user_info']['user_id'];

        try {
            $review = ReviewCourseService::getReview($studentId, $params['date']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['review' => $review]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function getTaskReview(Request $request, Response $response)
    {
        $params = $request->getParams();
        $studentId = $this->ci['user_info']['user_id'];

        try {
            $review = ReviewCourseService::getTaskReview($studentId, $params['task_id']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['review' => $review]);
    }
}