<?php

namespace Modules\RBAC\Http\Controllers\Api\RBAC;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use Spatie\Permission\Models\Role; // import laravel spatie permission models
use Modules\RBAC\Transformers\Dashboard as DashboardResource;

class DashboardController extends Controller
{
    public function getEmployees()
    {
        $employee = Employee::all();
        return response()->json($employee);
    }

    public function getPendingLeaves()
    {

    }
}
