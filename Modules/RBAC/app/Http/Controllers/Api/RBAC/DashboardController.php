<?php

namespace Modules\RBAC\Http\Controllers\Api\RBAC;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Carbon\Carbon;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveApproval;
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
        $employee = auth()->user()->employee;

        $subordinateIds = $employee->subordinates->pluck('subordinate_id')->flatten();

        $leaveRequests = QueryBuilder::for(LeaveRequest::class)
            ->whereIn('employee_id', $subordinateIds)
            ->where('is_trashed', false)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ])
            ->allowedSorts(['name', 'start_date', 'end_date', 'created_at']) // Allow sorting by these fields
            ->defaultSort('-created_at') // Default sorting by latest first
            ->with('employee.user') // Eager loading relationships
            ->get()
            ->map(function ($request) {
                $request->start_date_formatted = Carbon::parse($request->start_date)->format('d M y');
                $request->end_date_formatted = Carbon::parse($request->end_date)->format('d M y');
                return $request;
            });

        // Return response
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

        $leaves = LeaveRequest::with('employee.user')->whereNot('status','Rejected')->get();

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


    public function upcomingLeaves()
    {
        $leaves = LeaveRequest::with('employee.user')
            ->where('status', 'Approved')
            ->where('start_date', '>=', Carbon::now())
            ->where('start_date', '<=', Carbon::now()->addDays(15))
            ->get();

        return response()->json($leaves);
    }

    public function upcomingBirthdays()
    {
        $today = Carbon::today();

        $birthdays = Employee::with('user') // Eager load the related user (name)
            ->select('id', 'user_id', 'dob') // Only fetch necessary fields
            ->whereNotNull('dob') // Ensure dob is not null
            ->orderByRaw(
                "CASE
                    WHEN MONTH(dob) = ? AND DAY(dob) >= ? THEN 0
                    WHEN MONTH(dob) > ? THEN 0
                    ELSE 1
                END,
                MONTH(dob),
                DAY(dob)",
                [$today->month, $today->day, $today->month]
            )
        ->get();

        return response()->json($birthdays);
    }

    public function getUpcomingHoliday()
    {
        $today = Carbon::today();

        $holiday = Holiday::where('date', '>=', $today)
            ->where('date', '<=', $today->copy()->addDays(15))
            ->orderBy('date', 'asc')
            ->first();

        // Check if a holiday was found
        if ($holiday) {
            // Format the holiday date
            $holiday->date = Carbon::parse($holiday->date)->format('d-M-y');
        }

        return response()->json($holiday);
    }


    public function getEmployeeMonthlyLeaves(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|date_format:Y-m', // Format: YYYY-MM
        ]);

        // Extract values
        $employeeId = $request->employee_id;
        $month = $request->month;

        // Fetch leave requests with approvals (excluding rejected ones)
        $leaves = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', '!=', 'Rejected') // Exclude rejected leaves
            ->whereYear('start_date', date('Y', strtotime($month)))
            ->whereMonth('start_date', date('m', strtotime($month)))
            ->with(['employee.user', 'leaveType'])
            ->with(['leaveApprovals' => function ($query) {
                $query->whereIn('status', ['Approved', 'PartialApproved', 'ConditionalApproved', 'ApprovedWithoutPay']); // Include only approved statuses
            }])
            ->get();

        // Format response
        $response = $leaves->map(function ($leave) {
            return [
                'id' => $leave->id,
                'employee_id' => $leave->employee_id,
                'employee_name' => $leave->employee->user->name ?? 'N/A',
                'leave_type_id' => $leave->leavetype_id,
                'type_name' => $leave->leaveType->type_name ?? 'N/A',
                'start_date' => $leave->start_date,
                'end_date' => $leave->end_date,
                'status' => $leave->status,
                'total_days' => $leave->leaveApprovals->total_days ?? 0, // Sum of approved total_days
                'created_at' => $leave->created_at,
                'updated_at' => $leave->updated_at,
            ];
        });

        return response()->json([
            'employee_id' => $employeeId,
            'month' => $month,
            'leaves' => $response,
        ]);
    }




}
