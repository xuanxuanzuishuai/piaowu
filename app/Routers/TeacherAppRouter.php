<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:55 PM
 */

namespace App\Routers;

use App\Controllers\TeacherApp\App as TeacherAppApp;
use App\Controllers\TeacherApp\Auth;
use App\Controllers\TeacherApp\Note;
use App\Controllers\TeacherApp\Opn;
use App\Controllers\TeacherApp\Org;
use App\Controllers\TeacherApp\Play;
use App\Controllers\TeacherApp\Schedule;
use App\Middleware\AppApiForTeacher;
use App\Middleware\MUSVGMiddleWare;
use App\Middleware\OrgAuthCheckMiddleWareForApp;
use App\Middleware\OrgResPrivilegeCheckMiddleWareForApp;
use App\Middleware\OrgTeacherAuthMiddleWareForApp;
use App\Middleware\TeacherCheckMiddleWareForApp;


class TeacherAppRouter extends RouterBase
{
    protected $logFilename = 'dss_teacher.log';
    public $middleWares = [AppApiForTeacher::class];
    protected $uriConfig = [
        '/teacher_app/auth/login' => [
            'method' => ['post'],
            'call' => Auth::class . ':login',
            'middles' => [AppApiForTeacher::class]
        ],
        '/teacher_app/auth/token_login' => [
            'method' => ['post'],
            'call' => Auth::class . ':tokenLogin',
            'middles' => [AppApiForTeacher::class]
        ],
        '/teacher_app/auth/send_verify_code' => [
            'method' => ['post'],
            'call' => Auth::class . ':validateCode',
            'middles' => [AppApiForTeacher::class]
        ],
        '/teacher_app/org/teacher_list' => [
            'method' => ['get'],
            'call' => Org::class . ':teacherList',
            'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],
        '/teacher_app/org/get_students' => [
            'method' => ['get'],
            'call' => Org::class . ':getStudents',
            'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],
        '/teacher_app/org/select_student' => [
            'method' => ['post'],
            'call' => Org::class . ':selectStudent',
            'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],
        '/teacher_app/org/teacher_logout' => [
            'method' => ['post'],
            'call' => Org::class . ':teacherLogout',
            'middles' => [TeacherCheckMiddleWareForApp::class,
                OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],

        '/teacher_app/opn/search' => [
            'method' => ['get'],
            'call' => Opn::class . ':search',
            'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],
        '/teacher_app/opn/categories' => [
            'method' => ['get'],
            'call' => Opn::class . ':categories',
            'middles' => [OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],
        '/teacher_app/opn/collections' => [
            'method' => ['get'],
            'call' => Opn::class . ':collections',
            'middles' => [OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],
        '/teacher_app/opn/lessons' => [
            'method' => ['get'],
            'call' => Opn::class . ':lessons',
            'middles' => [OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],
        '/teacher_app/opn/lesson' => [
            'method' => ['get'],
            'call' => Opn::class . ':lesson',
            'middles' => [OrgResPrivilegeCheckMiddleWareForApp::class,
                OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],
        '/teacher_app/opn/recent_collections' => [
            'method' => ['get'],
            'call' => Opn::class . ':recentCollections',
            'middles' => [TeacherCheckMiddleWareForApp::class,
                OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],
        '/teacher_app/opn/recent_lessons' => [
            'method' => ['get'],
            'call' => Opn::class . ':recentLessons',
            'middles' => [TeacherCheckMiddleWareForApp::class,
                OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],
        '/teacher_app/opn/lesson_resource' => [
            'method' => ['get'],
            'call' => Opn::class . ':getLessonResource',
            'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],
        '/teacher_app/opn/knowledge' => [
            'method' => ['get'],
            'call' => Opn::class . ':getKnowledge',
            'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],
        '/teacher_app/schedule/end' => [
            'method' => ['post'],
            'call' => Schedule::class . ':end',
            'middles' => [TeacherCheckMiddleWareForApp::class,
                OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],
        '/teacher_app/play/end' => [
            'method' => ['post'],
            'call' => Play::class . ':end',
            'middles' => [TeacherCheckMiddleWareForApp::class,
                OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],
        '/teacher_app/play/ai_end' => [
            'method' => ['post'],
            'call' => Play::class . ':aiEnd',
            'middles' => [MUSVGMiddleWare::class]
        ],

        '/teacher_app/app/version' => [
            'method' => ['get'],
            'call' => TeacherAppApp::class . ':version',
            'middles' => [AppApiForTeacher::class]
        ],

        '/teacher_app/app/config' => [
            'method' => ['get'],
            'call' => TeacherAppApp::class . ':config',
            'middles' => [AppApiForTeacher::class]
        ],

        '/teacher_app/app/feedback' => [
            'method' => ['post'],
            'call' => TeacherAppApp::class . ':feedback',
            'middles' => [OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],

        '/teacher_app/app/heart_beat' => [
            'method' => ['get'],
            'call' => TeacherAppApp::class . ':heartBeat',
            'middles' => [TeacherCheckMiddleWareForApp::class,
                OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class,
                AppApiForTeacher::class]
        ],

        '/teacher_app/app/get_signature' => [
            'method' => ['get'],
            'call' => TeacherAppApp::class . ':getSignature',
            'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],
        '/teacher_app/note/create' => [
            'method' => ['post'],
            'call' => Note::class . ':createNote',
            'middles' => [OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],
        '/teacher_app/note/update' => [
            'method' => ['post'],
            'call' => Note::class . ':updateNote',
            'middles' => [OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],
        '/teacher_app/note/delete' => [
            'method' => ['post'],
            'call' => Note::class . ':deleteNote',
            'middles' => [OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],

        '/teacher_app/note/list' => [
            'method' => ['get'],
            'call' => Note::class . ':listNote',
            'middles' => [OrgTeacherAuthMiddleWareForApp::class,
                OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
        ],

    ];
}