<?php

namespace Modules\RBAC\Http\Controllers\Api\RBAC;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
            ->when($request->filled('statuses'), function ($query) use ($request) {
            $query->whereIn('status', $request->statuses);
            })
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
        $holidays = Holiday::withoutGlobalScope('activeYear')->where('is_active', 1)->get();

        $leaves = LeaveRequest::withoutGlobalScope('activeYear')->with('employee.user')->whereNot('status','Rejected')->where('is_trashed',0)->get();

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
                    'id' => $leave->id,
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
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'employee_id' => 'nullable|exists:employees,id',
            'status' => 'nullable|in:Approved,PartialApproved,ConditionalApproved,ApprovedWithoutPay'
        ]);

        $employeeId = $request->employee_id;
        $year = $request->year;
        $month = $request->month;
        $status = $request->status;

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

        // Build the base query
        $leavesQuery = LeaveRequest::where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_date', [$monthStart, $monthEnd])
                  ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                  ->orWhere(function ($sub) use ($monthStart, $monthEnd) {
                      $sub->where('start_date', '<', $monthStart)
                          ->where('end_date', '>', $monthEnd);
                  });
            })
            ->with(['employee.user', 'leaveType'])
            ->with(['leaveApprovals' => function ($query) {
                $query->whereIn('status', [
                    'Approved',
                    'PartialApproved',
                    'ConditionalApproved',
                    'ApprovedWithoutPay'
                ]);
            }]);

        if ($employeeId) {
            $leavesQuery->where('employee_id', $employeeId);
        }

        if ($status) {
            $leavesQuery->where('status', $status);
        }

        $leaves = $leavesQuery->get();

        // Group by employee and calculate totals
        $groupedLeaves = $leaves->groupBy('employee_id')->map(function ($leaveGroup) use ($monthStart, $monthEnd) {
            $employee = $leaveGroup->first()->employee;
            $user = $employee->user;

            $calculateDays = function ($startDate, $endDate, $isHalfDay) use ($monthStart, $monthEnd) {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);

                // Determine overlapping range with the current month
                $rangeStart = $start->greaterThan($monthStart) ? $start : $monthStart;
                $rangeEnd = $end->lessThan($monthEnd) ? $end : $monthEnd;

                if ($rangeEnd->lt($rangeStart)) {
                    return 0; // No overlap
                }

                $days = CarbonPeriod::create($rangeStart, $rangeEnd)->count();

                // Half-day correction
                if ($isHalfDay) {
                    $days = 0.5;
                }

                return $days;
            };

            return [
                'employee_id' => $employee->id,
                'employee_name' => $user->name ?? 'N/A',
                'total_leave_count' => $leaveGroup->count(),
                'total_leave_days' => round($leaveGroup->sum(function ($leave) use ($calculateDays) {
                    if ($leave->leaveApprovals->isNotEmpty()) {
                        return $leave->leaveApprovals->sum(function ($approval) use ($leave, $calculateDays) {
                            return $calculateDays($approval->start_date, $approval->end_date, $leave->is_half_day);
                        });
                    } elseif (in_array($leave->status, ['Approved', 'ApprovedWithoutPay', 'ConditionalApproved'])) {
                        return $calculateDays($leave->start_date, $leave->end_date, $leave->is_half_day);
                    }
                    return 0;
                }), 2),
                'leaves' => $leaveGroup->map(function ($leave) use ($calculateDays) {
                    $daysInMonth = 0;

                    if ($leave->leaveApprovals->isNotEmpty()) {
                        $daysInMonth = $leave->leaveApprovals->sum(function ($approval) use ($leave, $calculateDays) {
                            return $calculateDays($approval->start_date, $approval->end_date, $leave->is_half_day);
                        });
                    } elseif (in_array($leave->status, ['Approved', 'ApprovedWithoutPay', 'ConditionalApproved'])) {
                        $daysInMonth = $calculateDays($leave->start_date, $leave->end_date, $leave->is_half_day);
                    }

                    $daysInMonth = round($daysInMonth, 2);

                    return [
                        'id' => $leave->id,
                        'leave_type_id' => $leave->leavetype_id,
                        'type_name' => $leave->leaveType->type_name ?? 'N/A',
                        'start_date' => $leave->start_date,
                        'end_date' => $leave->end_date,
                        'status' => $leave->status,
                        'total_days_in_month' => $daysInMonth,
                        'created_at' => $leave->created_at,
                        'updated_at' => $leave->updated_at,
                    ];
                })
            ];
        });

        // ✅ Sort alphabetically by employee_name
        $sortedGroupedLeaves = $groupedLeaves->sortBy('employee_name')->values();

        return response()->json([
            'employee_id' => $employeeId,
            'year' => $year,
            'month' => $month,
            'status' => $status,
            'grouped_leaves' => $sortedGroupedLeaves
        ]);
    }


    //Check how many employees have upcoming confirmation_date in next 30 days by using hire_date from employees table. confirmation_date is hire_date + 6 months. if confirmation_date is null, it means employee is not yet confirmed.

    public function upcomingConfirmations()
    {
        $today = Carbon::today();
        $upcomingDate = $today->copy()->addDays(30);

        $employees = Employee::whereNotNull('hire_date')->where('confirmation_date', NULL)->with('user')
            ->get()
            ->filter(function ($employee) use ($today, $upcomingDate) {
                $confirmationDate = Carbon::parse($employee->hire_date)->addMonths(6);
                return $confirmationDate->between($today, $upcomingDate);
            });

        return response()->json($employees->values());
    }


    //Confirm Employee
    public function confirmEmployee(Request $request)
    {

        // Validate request parameters
        $request->validate([
            'employeeId' => 'required|exists:employees,id',
            'confirmation_date' => 'required|date',
        ]);

        // Find the employee
        $employee = Employee::findOrFail($request->employeeId);

        // Update confirmation date and status
        $employee->confirmation_date = $request->confirmation_date;
        $employee->save();

        return response()->json([
            'message' => 'Employee confirmed successfully.',
            'employee' => $employee,
        ]);
    }

    //Extend Confirmation
    public function extendConfirmation(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'employeeId' => 'required|exists:employees,id',
            'newDate' => 'required|date|after:confirmation_date',
        ]);

        // Find the employee
        $employee = Employee::findOrFail($request->employeeId);

        // Update confirmation date
        $employee->confirmation_date = $request->newDate;
        $employee->save();

        return response()->json([
            'message' => 'Employee confirmation extended successfully.',
            'employee' => $employee,
        ]);
    }

    public function revokeConfirmation(Request $request, $employeeId)
    {
        // Find the employee
        $employee = Employee::findOrFail($employeeId);

        // Revoke confirmation by setting confirmation_date to null
        $employee->confirmation_date = null;
        $employee->save();

        return response()->json([
            'message' => 'Employee confirmation revoked successfully.',
            'employee' => $employee,
        ]);
    }
}
