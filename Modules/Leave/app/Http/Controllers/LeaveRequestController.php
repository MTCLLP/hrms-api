<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Mail\NewLeaveRequestNotification;
use Modules\Leave\Mail\LeaveApprovalNotification;
use Modules\Leave\Mail\LeaveRejectionNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\LeaveEntitlement;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveApproval;
use Modules\Employee\Models\JobReporting;
use Carbon\Carbon;
use Modules\Leave\Transformers\LeaveRequestResource as LeaveRequestResource;
use Modules\Leave\Services\LeaveQuotaService;

class LeaveRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Determine query parameters
        $isTrashed = $request->boolean('trashed', false); // Defaults to false
        $isPaginated = $request->boolean('paginate', true); // Defaults to true
        $order = $request->get('order', 'desc'); // Defaults to 'desc'
        $page = $request->get('page', 1);
        $perPage = (int) $request->get('per_page', config('pagination.per_page'));

        // Start building query
        $leaveRequests = LeaveRequest::query();

        // Apply trashed filter if column exists
        if (Schema::hasColumn('leave_requests', 'is_trashed')) {
            $leaveRequests->where('is_trashed', $isTrashed);
        }

        // Show only requests for active employees and active users
        $leaveRequests->whereHas('employee', function ($eq) {
            $eq->where('is_active', true);
            if (Schema::hasColumn('users', 'is_active')) {
                $eq->whereHas('user', function ($uq) {
                    $uq->where('is_active', true);
                });
            } else {
                $eq->whereHas('user');
            }
        });

        // Role-based filtering
        if ($user->hasRole('Administrator') || $user->hasRole('Superadmin')) {
            // Admins see all leave requests
        } elseif ($user->hasRole('Manager')) {
            $managerEmployeeId = $user->employee->id;
            
            $subordinateIds = JobReporting::where('superior_id', $managerEmployeeId)
                ->where('is_active', true)
                ->pluck('subordinate_id');

            $leaveRequests->whereIn('employee_id', $subordinateIds->push($managerEmployeeId)); // Include manager's own requests
        } elseif ($user->hasRole('Employee')) {
            $leaveRequests->where('employee_id', $user->employee->id);
        } else {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        // Apply dynamic query filters
        if ($request->filled('start_date')) {
            $leaveRequests->whereDate('start_date', '>=', Carbon::parse($request->start_date));
        }

        if ($request->filled('end_date')) {
            $leaveRequests->whereDate('end_date', '<=', Carbon::parse($request->end_date));
        }

        if ($request->filled('status')) {
            $leaveRequests->where('status', $request->status);
        }

        if ($request->filled('leave_type')) {
            $leaveRequests->where('leavetype_id', $request->leave_type);
        }

        if ($request->filled('employee_id') && $user->hasRole('Administrator')) {
            $leaveRequests->where('employee_id', $request->employee_id);
        }

        // Apply ordering
        $leaveRequests->orderBy('created_at', $order);

        // Apply pagination if required
        if ($isPaginated) {
            $leaveRequests = $leaveRequests->paginate($perPage)->appends($request->query());
            return LeaveRequestResource::collection($leaveRequests);
        } else {
            $leaveRequests = $leaveRequests->get();
            return LeaveRequestResource::collection($leaveRequests);
        }
    }


    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $user = auth()->user();

        $query = LeaveRequest::query();
        if (Schema::hasColumn('leave_requests', 'is_trashed')) {
            $query->where('is_trashed', false);
        }

        // Show only requests for active employees and active users
        $query->whereHas('employee', function ($eq) {
            $eq->where('is_active', true);
            if (Schema::hasColumn('users', 'is_active')) {
                $eq->whereHas('user', function ($uq) {
                    $uq->where('is_active', true);
                });
            } else {
                $eq->whereHas('user');
            }
        });

        if ($user->hasRole('Administrator') or $user->hasRole('Superadmin')) {
            $leaveRequests = $query
                ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } elseif ($user->hasRole('Manager')) {
            $managerEmployeeId = $user->employee->id;

            $subordinateIds = JobReporting::where('superior_id', $managerEmployeeId)
                ->where('is_active', true)
                ->pluck('subordinate_id');

            $leaveRequests = $query
                ->whereIn('employee_id', $subordinateIds->push($managerEmployeeId))
                ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            $employeeId = $user->employee ? $user->employee->id : null;

            $leaveRequests = $query
                ->where('employee_id', $employeeId)
                ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }

        return LeaveRequestResource::collection($leaveRequests);
    }



    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $employeeId = auth()->user()->employee->id;
        $employee = auth()->user()->employee;

        // Parse dates using Carbon
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        // Check if start_date or end_date falls on a weekend
        if ($startDate->isWeekend() || $endDate->isWeekend()) {
            return response()->json([
                'status' => false,
                'message' => 'Leave cannot start or end on a weekend',
                'errors' => ['Error' => ['Start date and end date cannot be a Saturday or Sunday.']]
            ], 400);
        }

        // Check for overlapping leave requests
        $overlapQuery = LeaveRequest::where('employee_id', $employeeId);
        if (Schema::hasColumn('leave_requests', 'is_active')) {
            $overlapQuery->where('is_active', 1);
        }
        $overlap = $overlapQuery
            ->whereNot('status', 'Rejected')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'status' => false,
                'message' => 'Leave overlaps',
                'errors' => ['Error' => ['Leave request overlaps with an existing request.']]
            ], 422);
        }

        // Calculate the number of days (inclusive)
        $isHalfDay = $request->input('is_half_day', false); // Defaults to false if not provided

        if ($isHalfDay) {
            if (!$startDate->equalTo($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Half day can only be for a single day',
                    'errors' => ['Error' => ['Half day can only be for a single day.']]
                ], 400);
            }
            $numberOfDays = 0.5;
        } else {
            $numberOfDays = (int)($startDate->diffInDays($endDate)) + 1;
        }

        if ($numberOfDays > 5) {
            return response()->json([
                'status' => false,
                'message' => 'Too many leaves',
                'errors' => ['Error' => ['You can only take 5 days of leave at once!.']]
            ], 400);
        }

        $selectedLeaveTypeId = $request->input('selectedLeaveType');

        // Calculate split deductions across available quotas immediately upon application
        $calc = LeaveQuotaService::calculateDeductions(
            $employeeId,
            $selectedLeaveTypeId,
            $numberOfDays
        );

        if (!$calc['success']) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient leave balance',
                'errors' => [
                    'Error' => [
                        'Insufficient leave balance across all available quotas (Restricted Holiday, Casual/Earned, Sick Leave). Total available: ' . $calc['total_allocated'] . ' day(s), requested: ' . $numberOfDays . ' day(s).'
                    ]
                ]
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Deduct quotas immediately upon application
            LeaveQuotaService::applyDeductions($calc['deductions'], $employeeId);

            $deductionRemarks = LeaveQuotaService::formatDeductionSummary($calc['deductions']);

            $leaveData = [
                'employee_id' => $employeeId,
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'leavetype_id' => $selectedLeaveTypeId,
                'is_half_day' => $isHalfDay,
                'leave_description' => $request->input('leave_description'),
                'reason' => $request->input('reason'),
                'comments' => $deductionRemarks,
                'created_by' => auth()->user()->id,
            ];

            if (Schema::hasColumn('leave_requests', 'is_active')) {
                $leaveData['is_active'] = 1;
            }

            if (Schema::hasColumn('leave_requests', 'is_trashed')) {
                $leaveData['is_trashed'] = 0;
            }

            if (Schema::hasColumn('leave_requests', 'is_deducted')) {
                $leaveData['is_deducted'] = true;
            }

            if (Schema::hasColumn('leave_requests', 'deductions')) {
                $leaveData['deductions'] = $calc['deductions'];
            }

            $leaveRequests = LeaveRequest::create($leaveData);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        if (!(auth()->user()->id == 21 || auth()->user()->id == 22)) {
            // Notify all superiors
            $superiors = $employee->superiors;

            if ($superiors) {
                foreach ($superiors as $superior) {
                    $superiorDetails = $superior->superiorDetails;

                    if ($superiorDetails && $superiorDetails->user && $superiorDetails->user->email) {
                        Mail::to($superiorDetails->user->email)
                            ->send(new NewLeaveRequestNotification($leaveRequests, $employee));
                    }
                }
            }
        }

        return new LeaveRequestResource($leaveRequests);
    }

    /**
     * Approve leave request.
     * @param Request $request
     * @return Renderable
     */
    public function approveLeave(Request $request)
    {
        $leaveId = $request->input('leave_request_id');
        $action = $request->input('action');
        $leave = LeaveRequest::find($leaveId);

        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave not found',
                'errors' => ['Error' => ['Leave request not found.']]
            ], 404);
        }

        $approver = auth()->user();
        $startDate = Carbon::parse($leave->start_date);
        $endDate = Carbon::parse($leave->end_date);
        $numberOfDays = $leave->is_half_day ? 0.5 : ($startDate->diffInDays($endDate)) + 1;

        // Determine if the leave is paid or unpaid
        $isPaidLeave = ($action !== 'ApprovedWithoutPay');

        $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
        $wasDeducted = $hasIsDeducted ? $leave->is_deducted : false;
        $deductionRemarks = '';

        if (!$isPaidLeave) {
            // Approved without pay: if it was previously deducted, restore it back
            if ($wasDeducted) {
                LeaveQuotaService::revertDeductions($leave, $numberOfDays);
            }
        } else {
            // Paid leave: if already deducted upon application, get summary from stored deductions
            if ($wasDeducted) {
                if (!empty($leave->deductions)) {
                    $deductionRemarks = LeaveQuotaService::formatDeductionSummary($leave->deductions);
                }
            } else {
                // If not yet deducted (e.g. legacy pending leave), calculate and apply split deductions
                $calc = LeaveQuotaService::calculateDeductions(
                    $leave->employee_id,
                    $leave->leavetype_id,
                    $numberOfDays
                );

                if (!$calc['success']) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Insufficient leave balance',
                        'errors' => [
                            'Error' => [
                                'Insufficient leave balance across all available quotas (Restricted Holiday, Casual/Earned, Sick Leave). Total available: ' . $calc['total_allocated'] . ' day(s), requested: ' . $numberOfDays . ' day(s). You can approve this request as "Approve Without Pay".'
                            ]
                        ]
                    ], 400);
                }

                LeaveQuotaService::applyDeductions($calc['deductions'], $leave->employee_id);

                if ($hasIsDeducted) {
                    $leave->is_deducted = true;
                }
                if (Schema::hasColumn('leave_requests', 'deductions')) {
                    $leave->deductions = $calc['deductions'];
                }
                $deductionRemarks = LeaveQuotaService::formatDeductionSummary($calc['deductions']);
            }
        }

        // Set leave status based on approval type
        switch ($action) {
            case 'ApprovedWithoutPay':
                $leave->status = 'ApprovedWithoutPay';
                break;
            case 'ConditionalApproved':
                $leave->status = 'ConditionalApproved';
                break;
            case 'PartialApproved':
                $leave->status = 'PartialApprove';
                break;
            case 'Approved':
            default:
                $leave->status = 'Approved';
                break;
        }

        $leave->supervised_by = auth()->user()->id;
        $leave->save();

        $userComment = $request->input('comment');
        $remarks = $userComment ? $userComment : '';
        if ($deductionRemarks) {
            $remarks = $remarks ? "{$remarks} ({$deductionRemarks})" : $deductionRemarks;
        }

        // Create approval record
        LeaveApproval::create([
            'leaverequest_id' => $leave->id,
            'approver_id' => auth()->user()->employee ? auth()->user()->employee->id : null,
            'start_date' => $leave->start_date,
            'end_date' => $leave->end_date,
            'total_days' => $numberOfDays,
            'isPaidLeave' => $isPaidLeave,
            'status' => $leave->status,
            'remarks' => $remarks,
        ]);

        // Send notification
        if ($leave->employee && $leave->employee->user && $leave->employee->user->email) {
            Mail::to($leave->employee->user->email)
                ->send(new LeaveApprovalNotification($leave, $approver));
        }

        return response()->json(['message' => 'Leave request approved successfully.'], 200);
    }




    public function rejectLeave(Request $request)
    {
        $leaveId = $request->input('leave_request_id');
        $leave = LeaveRequest::find($leaveId);

        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave not found',
                'errors' => ['Error' => ['Leave request not found.']]
            ], 404);
        }

        // If user does not have role of "Administrator" or "Superadmin", check if they are the superior
        $user = auth()->user();
        if (!$user->hasRole(['Administrator', 'Superadmin'])) {
            if ($user->id !== $leave->supervised_by) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized',
                    'errors' => ['Error' => ['You are not authorized to reject this leave request.']]
                ], 403);
            }
        }

        $approver = auth()->user();

        // Revert back the available count if leave was deducted and not already rejected or without pay
        if ($leave->status !== 'Rejected' && $leave->status !== 'ApprovedWithoutPay') {
            $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
            $wasDeducted = $hasIsDeducted ? $leave->is_deducted : true;

            if ($wasDeducted) {
                $startDate = Carbon::parse($leave->start_date);
                $endDate = Carbon::parse($leave->end_date);
                $numberOfDays = $leave->is_half_day ? 0.5 : ($startDate->diffInDays($endDate)) + 1;

                LeaveQuotaService::revertDeductions($leave, $numberOfDays);
            }
        }

        // Set leave status to 'Rejected'
        $leave->status = 'Rejected';
        $leave->supervised_by = auth()->user()->id;
        $leave->save();

        // Store the rejection in the leave_approvals table
        LeaveApproval::create([
            'leaverequest_id' => $leave->id,
            'approver_id' => auth()->user()->employee ? auth()->user()->employee->id : null,
            'start_date' => $leave->start_date,
            'end_date' => $leave->end_date,
            'total_days' => 0, // No days deducted for rejection
            'isPaidLeave' => false,
            'status' => 'Rejected',
            'remarks' => $request->input('comment'),
        ]);

        // Notify the employee
        if ($leave->employee && $leave->employee->user && $leave->employee->user->email) {
            Mail::to($leave->employee->user->email)
                ->send(new LeaveRejectionNotification($leave, $approver));
        }

        return response()->json(['message' => 'Leave request rejected successfully.'], 200);
    }



    public function partialApproval(Request $request)
    {
        $leaveId = $request->input('leave_request_id');
        $leave = LeaveRequest::find($leaveId);

        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave not found',
                'errors' => ['Error' => ['Leave request not found.']]
            ], 404);
        }

        $approver = auth()->user();
        $partialApprovals = $request->input('partial_approvals', []);

        if (empty($partialApprovals)) {
            return response()->json([
                'status' => false,
                'message' => 'Data not provided',
                'errors' => ['Error' => ['No partial approval data provided.']]
            ], 400);
        }

        $startDateOriginal = Carbon::parse($leave->start_date);
        $endDateOriginal = Carbon::parse($leave->end_date);
        $originalNumberOfDays = $leave->is_half_day ? 0.5 : ($startDateOriginal->diffInDays($endDateOriginal)) + 1;

        // Calculate total approved paid days across partial approval periods
        $approvedPaidDays = 0.0;
        foreach ($partialApprovals as $approval) {
            $startDate = Carbon::parse($approval['start_date']);
            $endDate = Carbon::parse($approval['end_date']);
            $status = $approval['status'];
            $days = (float)($startDate->diffInDays($endDate) + 1);

            $isPaid = ($status !== 'ApprovedWithoutPay' && $status !== 'Rejected');
            if ($isPaid) {
                $approvedPaidDays += $days;
            }
        }

        $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
        $wasDeducted = $hasIsDeducted ? $leave->is_deducted : true;

        DB::beginTransaction();
        try {
            // 1. Revert original deduction so baseline quotas are fully restored
            if ($wasDeducted) {
                LeaveQuotaService::revertDeductions($leave, $originalNumberOfDays);
            }

            // 2. For partial approval, re-apply priority deduction for the approved paid days
            $deductionRemarks = '';
            if ($approvedPaidDays > 0) {
                $calc = LeaveQuotaService::calculateDeductions(
                    $leave->employee_id,
                    $leave->leavetype_id,
                    $approvedPaidDays
                );

                if (!$calc['success']) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'Insufficient leave balance for partial approval',
                        'errors' => [
                            'Error' => [
                                'Insufficient leave balance across available quotas. Total available: ' . $calc['total_allocated'] . ' day(s), approved paid: ' . $approvedPaidDays . ' day(s).'
                            ]
                        ]
                    ], 400);
                }

                LeaveQuotaService::applyDeductions($calc['deductions'], $leave->employee_id);

                if ($hasIsDeducted) {
                    $leave->is_deducted = true;
                }
                if (Schema::hasColumn('leave_requests', 'deductions')) {
                    $leave->deductions = $calc['deductions'];
                }
                $deductionRemarks = LeaveQuotaService::formatDeductionSummary($calc['deductions']);
            } else {
                if ($hasIsDeducted) {
                    $leave->is_deducted = false;
                }
                if (Schema::hasColumn('leave_requests', 'deductions')) {
                    $leave->deductions = null;
                }
            }

            // Create approval record for each partial approval period
            $userComment = $request->input('comment');
            foreach ($partialApprovals as $approval) {
                $startDate = Carbon::parse($approval['start_date']);
                $endDate = Carbon::parse($approval['end_date']);
                $status = $approval['status'];
                $numberOfDays = $startDate->diffInDays($endDate) + 1;
                $isPaidLeave = ($status !== 'ApprovedWithoutPay' && $status !== 'Rejected');

                $periodRemarks = $userComment ? $userComment : '';
                if ($isPaidLeave && $deductionRemarks) {
                    $periodRemarks = $periodRemarks ? "{$periodRemarks} ({$deductionRemarks})" : $deductionRemarks;
                }

                LeaveApproval::create([
                    'leaverequest_id' => $leave->id,
                    'approver_id' => auth()->user()->employee ? auth()->user()->employee->id : null,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_days' => $numberOfDays,
                    'isPaidLeave' => $isPaidLeave,
                    'status' => in_array($status, ['Pending', 'Approved', 'Rejected', 'ApprovedWithoutPay']) ? $status : 'Approved',
                    'remarks' => $periodRemarks,
                ]);
            }

            $leave->status = 'Partially Approved';
            $leave->supervised_by = auth()->user()->id;
            $leave->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['message' => 'Leave request partially approved successfully.'], 200);
    }



    /**
     * Show the specified resource.
     */
    public function show(LeaveRequest $leaveRequest)
    {
        return new LeaveRequestResource($leaveRequest);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeaveRequest $leaveRequest)
    {
        $employeeId = auth()->user()->employee->id;

        // Parse dates using Carbon
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        // Check for overlapping leave requests
        $overlapQuery = LeaveRequest::where('employee_id', $employeeId)
            ->where('id', '!=', $leaveRequest->id);
        if (Schema::hasColumn('leave_requests', 'is_active')) {
            $overlapQuery->where('is_active', 1);
        }
        $overlap = $overlapQuery
            ->whereNot('status', 'Rejected')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json(['message' => 'Leave request overlaps with an existing request.'], 422);
        }

        $isHalfDay = $request->input('is_half_day', false);
        $newNumberOfDays = $isHalfDay ? 0.5 : (int)($startDate->diffInDays($endDate)) + 1;
        $newLeaveTypeId = $request->input('selectedLeaveType');

        if ($newNumberOfDays > 5) {
            return response()->json(['error' => 'You can only take 5 days of leave at once!.'], 400);
        }

        // Adjust balance if updating a pending leave request that was deducted
        if ($leaveRequest->status === 'Pending') {
            $oldStartDate = Carbon::parse($leaveRequest->start_date);
            $oldEndDate = Carbon::parse($leaveRequest->end_date);
            $oldNumberOfDays = $leaveRequest->is_half_day ? 0.5 : (int)($oldStartDate->diffInDays($oldEndDate)) + 1;

            $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
            $wasDeducted = $hasIsDeducted ? $leaveRequest->is_deducted : false;

            if ($wasDeducted) {
                LeaveQuotaService::revertDeductions($leaveRequest, $oldNumberOfDays);
            }

            // Calculate new deductions for updated dates/type
            $calc = LeaveQuotaService::calculateDeductions(
                $employeeId,
                $newLeaveTypeId,
                $newNumberOfDays
            );

            if (!$calc['success']) {
                // Restore previous deductions before returning error
                if ($wasDeducted && !empty($leaveRequest->deductions)) {
                    LeaveQuotaService::applyDeductions($leaveRequest->deductions, $employeeId);
                    $leaveRequest->is_deducted = true;
                    $leaveRequest->save();
                }
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient leave balance',
                    'errors' => [
                        'Error' => [
                            'Insufficient leave balance across available quotas. Available: ' . $calc['total_allocated'] . ' day(s), requested: ' . $newNumberOfDays . ' day(s).'
                        ]
                    ]
                ], 400);
            }

            LeaveQuotaService::applyDeductions($calc['deductions'], $employeeId);
            if ($hasIsDeducted) {
                $leaveRequest->is_deducted = true;
            }
            if (Schema::hasColumn('leave_requests', 'deductions')) {
                $leaveRequest->deductions = $calc['deductions'];
            }
        }

        $leaveRequest->employee_id = $employeeId;
        $leaveRequest->leavetype_id = $newLeaveTypeId;
        $leaveRequest->start_date = $request->input('start_date');
        $leaveRequest->end_date = $request->input('end_date');
        $leaveRequest->is_half_day = $isHalfDay;
        $leaveRequest->leave_description = $request->input('leave_description');
        $leaveRequest->reason = $request->input('reason');
        $leaveRequest->save();

        return new LeaveRequestResource($leaveRequest);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        $hasIsTrashed = Schema::hasColumn('leave_requests', 'is_trashed');
        $is_trashed = $hasIsTrashed ? $leaveRequest->is_trashed : 0;

        if ($is_trashed == 1) {
            $leaveRequest->delete();
        } else {
            // Revert deducted balance when trashing if active and not rejected/unpaid
            if (!in_array($leaveRequest->status, ['Rejected', 'ApprovedWithoutPay'])) {
                $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
                $wasDeducted = $hasIsDeducted ? $leaveRequest->is_deducted : true;

                if ($wasDeducted) {
                    $startDate = Carbon::parse($leaveRequest->start_date);
                    $endDate = Carbon::parse($leaveRequest->end_date);
                    $numberOfDays = $leaveRequest->is_half_day ? 0.5 : ($startDate->diffInDays($endDate)) + 1;

                    LeaveQuotaService::revertDeductions($leaveRequest, $numberOfDays);
                }
            }

            if ($hasIsTrashed) {
                $leaveRequest->is_trashed = '1';
                if (Schema::hasColumn('leave_requests', 'deleted_at')) {
                    $leaveRequest->deleted_at = \Carbon\Carbon::now();
                }
                $leaveRequest->save();
            } else {
                $leaveRequest->delete();
            }
        }

        return response()->json([
            "message" => "Leave Request deleted"
        ], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $leaveRequests = LeaveRequest::where('is_trashed', true)->get();

        return LeaveRequestResource::collection($leaveRequests);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        // When restoring from trash, re-deduct if status was active
        if (!in_array($leaveRequest->status, ['Rejected', 'ApprovedWithoutPay'])) {
            $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
            $wasDeducted = $hasIsDeducted ? $leaveRequest->is_deducted : false;

            if (!$wasDeducted) {
                $startDate = Carbon::parse($leaveRequest->start_date);
                $endDate = Carbon::parse($leaveRequest->end_date);
                $numberOfDays = $leaveRequest->is_half_day ? 0.5 : ($startDate->diffInDays($endDate)) + 1;

                $leaveBalance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                    ->where('leavetype_id', $leaveRequest->leavetype_id)
                    ->first();

                if ($leaveBalance) {
                    $leaveBalance->balance_amount = (float)$leaveBalance->balance_amount - (float)$numberOfDays;
                    $leaveBalance->save();
                }

                if ($hasIsDeducted) {
                    $leaveRequest->is_deducted = true;
                }
            }
        }

        $leaveRequest->is_trashed = '0';
        $leaveRequest->deleted_at = null;
        $leaveRequest->save();

        return response()->json([
            "message" => "Leave Request restored successfully"
        ], 202);
    }

    /**
     * Remove multiple specified resources from storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids;

        if (empty($ids)) {
            return response()->json([
                "message" => "No department IDs provided"
            ], 400);
        }

        $leaveRequests = LeaveRequest::whereIn('id', $ids)->get();

        $deletedPermanently = [];
        $softDeleted = [];

        $hasIsTrashed = Schema::hasColumn('leave_requests', 'is_trashed');

        foreach ($leaveRequests as $leaveRequest) {
            $is_trashed = $hasIsTrashed ? $leaveRequest->is_trashed : 0;

            if ($is_trashed == 1) {
                $leaveRequest->delete();
                $deletedPermanently[] = $leaveRequest->id;
            } else {
                if (!in_array($leaveRequest->status, ['Rejected', 'ApprovedWithoutPay'])) {
                    $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
                    $wasDeducted = $hasIsDeducted ? $leaveRequest->is_deducted : true;

                    if ($wasDeducted) {
                        $startDate = Carbon::parse($leaveRequest->start_date);
                        $endDate = Carbon::parse($leaveRequest->end_date);
                        $numberOfDays = $leaveRequest->is_half_day ? 0.5 : ($startDate->diffInDays($endDate)) + 1;

                        LeaveQuotaService::revertDeductions($leaveRequest, $numberOfDays);
                    }
                }

                if ($hasIsTrashed) {
                    $leaveRequest->is_trashed = '1';
                    if (Schema::hasColumn('leave_requests', 'deleted_at')) {
                        $leaveRequest->deleted_at = \Carbon\Carbon::now();
                    }
                    $leaveRequest->save();
                    $softDeleted[] = $leaveRequest->id;
                } else {
                    $leaveRequest->delete();
                    $deletedPermanently[] = $leaveRequest->id;
                }
            }
        }

        return response()->json([
            "message" => "Leave Request processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

    public function getEmployeeLeaves(Request $request, $employeeId)
    {
        $user = auth()->user();

        // Ensure the user is authorized to view this employee's leaves
        if ($user->hasRole('Administrator') || $user->hasRole('Superadmin') || ($user->hasRole('Employee') && $user->employee->id == $employeeId)) {
            $leaveRequests = LeaveRequest::where('employee_id', $employeeId)
                ->where('is_trashed', false)
                ->orderBy('created_at', 'desc')
                ->get();

            return LeaveRequestResource::collection($leaveRequests);
        } else {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }
    }
}
