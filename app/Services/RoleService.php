<?php

namespace App\Services;

use App\Models\RoleModel;

class RoleService
{

    public static function getById($id) {
        return RoleModel::getById($id);
    }
}