<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/3
 * Time: 10:46 AM
 */

namespace App\Controllers\StudentWeb;

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
    public function getTaskReview(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $review = ReviewCourseService::getTaskReview(NULL, $params['task_id']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['review' => $review]);
    }

}