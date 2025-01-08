<?php

namespace Modules\RBAC\Http\Controllers\Api\RBAC;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Spatie\Permission\Models\Role; // import laravel spatie permission models
use Modules\RBAC\Transformers\Dashboard as DashboardResource;

class DashboardController extends Controller
{
    public function getEmployees()
    {
        $employee = Employee::count();
        return response()->json($employee);
    }

    public function getPendingLeaves()
    {
        $employee = auth()->user()->employee;
        $leaves = $employee->leaveRequests()->where('status', 'pending')->count();

        return response()->json($leaves);
    }

    public function getSubordinateLeaves()
    {
        // Get the logged-in employee
        $employee = auth()->user()->employee;

        // Get subordinate IDs
        $subordinateIds = $employee->subordinates->pluck('subordinate_id')->flatten();
        // dd($subordinateIds);
        // Fetch leave requests with employee and user relationships
        $leaveRequests = LeaveRequest::with('employee.user')
                            ->whereIn('employee_id', $subordinateIds)
                            ->get();

        // Return the response
        return response()->json($leaveRequests);
    }

    function getLastLeave()
    {
        $employee = auth()->user()->employee;
        $lastLeave = $employee->leaveRequests()->latest();
        dd($lastLeave);
        return response()->json($lastLeave);
    }



}
