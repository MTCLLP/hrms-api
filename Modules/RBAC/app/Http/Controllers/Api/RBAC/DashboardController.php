<?php

namespace Modules\RBAC\Http\Controllers\Api\RBAC;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Carbon\Carbon;
use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\Holiday;
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

    public function getSubordinateLeaves(Request $request)
    {
        // Get the logged-in employee
        $employee = auth()->user()->employee;

        // Get subordinate IDs
        $subordinateIds = $employee->subordinates->pluck('subordinate_id')->flatten();

        // Get leave request statuses from the request query, default to empty array (no filter)
        $statuses = $request->query('statuses', []);
        if (!is_array($statuses)) {
            $statuses = explode(',', $statuses); // Ensure it's always an array
        }

        // Start the query for leave requests
        $leaveRequestsQuery = LeaveRequest::with('employee.user')
            ->whereIn('employee_id', $subordinateIds);

        // If statuses are provided, filter by status
        if (!empty($statuses)) {
            $leaveRequestsQuery->whereIn('status', $statuses);
        }

        // Fetch the leave requests, ordered by created_at
        $leaveRequests = $leaveRequestsQuery
            ->orderBy('created_at', 'desc')
            ->where('is_trashed','false')
            ->get()
            ->map(function ($request) {
                $request->start_date_formatted = Carbon::parse($request->start_date)->format('d M y');
                $request->end_date_formatted = Carbon::parse($request->end_date)->format('d M y');
                return $request;
            });

        // Return the response
        return response()->json($leaveRequests);
    }



    function getLastLeave()
    {
        $employee = auth()->user()->employee;
        $lastLeave = $employee->leaveRequests()->latest()->first();
        return response()->json($lastLeave);
    }

    function displayCalendar()
    {
        $holidays = Holiday::where('is_active', 1)->get();

        $leaves = LeaveRequest::with('employee.user')->where('status','Approved')->get();

        $calendarData = [];

        // Add holidays
        foreach ($holidays as $holiday) {
            $calendarData[] = [
                'type' => 'holiday',
                'title' => $holiday->name,
                'start' => $holiday->date, // Use 'start' for compatibility with frontend
                'end' => $holiday->date,   // Single-day events can have the same 'start' and 'end'
                'className' => 'holiday-event',
            ];
        }

        // Add leaves
        foreach ($leaves as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date);

            while ($startDate <= $endDate) {
                $calendarData[] = [
                    'type' => 'leave',
                    'title' => $leave->employee->user->name . "'s Leave",
                    'start' => $startDate->toDateString(), // Each day within the range
                    'end' => $startDate->toDateString(),   // Same as 'start' for single-day events
                    'className' => 'leave-event',
                ];
                $startDate->addDay(); // Move to the next day
            }
        }

        // Sort the calendar data by start date
        usort($calendarData, function ($a, $b) {
            return strtotime($a['start']) - strtotime($b['start']);
        });

        return response()->json($calendarData);
    }





}
