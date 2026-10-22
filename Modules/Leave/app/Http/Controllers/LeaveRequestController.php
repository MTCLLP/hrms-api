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

        // Apply trashed filter
        $leaveRequests->where('is_trashed', $isTrashed);

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

        if ($user->hasRole('Administrator') or $user->hasRole('Superadmin')) {
            $leaveRequests = LeaveRequest::where('is_trashed', false)
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } elseif ($user->hasRole('Manager')) {
            $managerEmployeeId = $user->employee->id;

            $subordinateIds = JobReporting::where('superior_id', $managerEmployeeId)
                ->where('is_active', true)
                ->pluck('subordinate_id');

            $leaveRequests = LeaveRequest::where('is_trashed', false)
                ->whereIn('employee_id', $subordinateIds->push($managerEmployeeId)) // Add manager's own ID
                ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } elseif ($user->hasRole('Employee')) {
            $employeeId = $user->employee->id;

            $leaveRequests = LeaveRequest::where('is_trashed', false)
                ->where('employee_id', $employeeId)
                ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            $employeeId = $user->employee->id;

            $leaveRequests = LeaveRequest::where('is_trashed', false)
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
        $overlap = LeaveRequest::where('employee_id', $employeeId)
            ->where('is_active', 1) // Only active leaves
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
        $primaryLeaveBalance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leavetype_id', $selectedLeaveTypeId)
            ->first();

        if (!$primaryLeaveBalance || (float)$primaryLeaveBalance->balance_amount < (float)$numberOfDays) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient leave balance',
                'errors' => ['Error' => ['Insufficient leave balance. You only have ' . ($primaryLeaveBalance ? $primaryLeaveBalance->balance_amount : 0) . ' day(s) available.']]
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Deduct leave balance upon applying
            $primaryLeaveBalance->balance_amount = (float)$primaryLeaveBalance->balance_amount - (float)$numberOfDays;
            $primaryLeaveBalance->save();

            $leaveData = [
                'employee_id' => $employeeId,
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'leavetype_id' => $selectedLeaveTypeId,
                'is_half_day' => $isHalfDay,
                'leave_description' => $request->input('leave_description'),
                'reason' => $request->input('reason'),
                'created_by' => auth()->user()->id,
                'is_active' => 1,
                'is_trashed' => 0,
            ];

            if (Schema::hasColumn('leave_requests', 'is_deducted')) {
                $leaveData['is_deducted'] = true;
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

        $primaryLeaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
            ->where('leavetype_id', $leave->leavetype_id)
            ->first();

        $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
        $wasDeducted = $hasIsDeducted ? $leave->is_deducted : true;

        if (!$isPaidLeave) {
            // Approved without pay: if it was deducted when applied, restore it back
            if ($wasDeducted && $primaryLeaveBalance) {
                $primaryLeaveBalance->balance_amount = (float)$primaryLeaveBalance->balance_amount + (float)$numberOfDays;
                $primaryLeaveBalance->save();
            }
            if ($hasIsDeducted) {
                $leave->is_deducted = false;
            }
        } else {
            // If it was not yet deducted (e.g. legacy pending leave created prior to this logic)
            if ($hasIsDeducted && !$leave->is_deducted) {
                if ($primaryLeaveBalance && (float)$primaryLeaveBalance->balance_amount >= (float)$numberOfDays) {
                    $primaryLeaveBalance->balance_amount = (float)$primaryLeaveBalance->balance_amount - (float)$numberOfDays;
                    $primaryLeaveBalance->save();
                }
                $leave->is_deducted = true;
            }
            // For leaves already deducted at apply time: do not update/deduct the count again
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

        // Create approval record
        LeaveApproval::create([
            'leaverequest_id' => $leave->id,
            'approver_id' => auth()->user()->employee ? auth()->user()->employee->id : null,
            'start_date' => $leave->start_date,
            'end_date' => $leave->end_date,
            'total_days' => $numberOfDays,
            'isPaidLeave' => $isPaidLeave,
            'status' => $leave->status,
            'remarks' => $request->input('comment'),
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

                $primaryLeaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
                    ->where('leavetype_id', $leave->leavetype_id)
                    ->first();

                if ($primaryLeaveBalance) {
                    $primaryLeaveBalance->balance_amount = (float)$primaryLeaveBalance->balance_amount + (float)$numberOfDays;
                    $primaryLeaveBalance->save();
                }

                if ($hasIsDeducted) {
                    $leave->is_deducted = false;
                }
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

        $primaryLeaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
            ->where('leavetype_id', $leave->leavetype_id)
            ->first();

        foreach ($partialApprovals as $approval) {
            $startDate = Carbon::parse($approval['start_date']);
            $endDate = Carbon::parse($approval['end_date']);
            $status = $approval['status'];
            $numberOfDays = $startDate->diffInDays($endDate) + 1;

            // Determine if it's a paid leave
            $isPaidLeave = ($status !== 'ApprovedWithoutPay' && $status !== 'Rejected');

            // If not paid (rejected or without pay), revert those days back to balance
            if (!$isPaidLeave && $primaryLeaveBalance) {
                $primaryLeaveBalance->balance_amount = (float)$primaryLeaveBalance->balance_amount + (float)$numberOfDays;
                $primaryLeaveBalance->save();
            }

            // Create approval record
            LeaveApproval::create([
                'leaverequest_id' => $leave->id,
                'approver_id' => auth()->user()->employee ? auth()->user()->employee->id : null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $numberOfDays,
                'isPaidLeave' => $isPaidLeave,
                'status' => $status,
                'remarks' => $request->input('comment'),
            ]);
        }

        $leave->status = 'Partially Approved';
        $leave->supervised_by = auth()->user()->id;
        $leave->save();

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
        $overlap = LeaveRequest::where('employee_id', $employeeId)
            ->where('id', '!=', $leaveRequest->id)
            ->where('is_active', 1) // Only active leaves
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
            $oldLeaveTypeId = $leaveRequest->leavetype_id;

            $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
            $wasDeducted = $hasIsDeducted ? $leaveRequest->is_deducted : true;

            if ($wasDeducted) {
                // Temporarily revert old balance
                $oldBalance = LeaveBalance::where('employee_id', $employeeId)
                    ->where('leavetype_id', $oldLeaveTypeId)
                    ->first();
                if ($oldBalance) {
                    $oldBalance->balance_amount = (float)$oldBalance->balance_amount + (float)$oldNumberOfDays;
                    $oldBalance->save();
                }

                // Check if new leave type has sufficient balance
                $newBalance = LeaveBalance::where('employee_id', $employeeId)
                    ->where('leavetype_id', $newLeaveTypeId)
                    ->first();

                if (!$newBalance || (float)$newBalance->balance_amount < (float)$newNumberOfDays) {
                    // Revert old deduction back before returning error
                    if ($oldBalance) {
                        $oldBalance->balance_amount = (float)$oldBalance->balance_amount - (float)$oldNumberOfDays;
                        $oldBalance->save();
                    }
                    return response()->json(['error' => 'Insufficient leave balance!'], 400);
                }

                // Deduct new days
                $newBalance->balance_amount = (float)$newBalance->balance_amount - (float)$newNumberOfDays;
                $newBalance->save();
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

        $is_trashed = $leaveRequest->is_trashed;

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

                    $leaveBalance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                        ->where('leavetype_id', $leaveRequest->leavetype_id)
                        ->first();

                    if ($leaveBalance) {
                        $leaveBalance->balance_amount = (float)$leaveBalance->balance_amount + (float)$numberOfDays;
                        $leaveBalance->save();
                    }

                    if ($hasIsDeducted) {
                        $leaveRequest->is_deducted = false;
                    }
                }
            }

            $leaveRequest->is_trashed = '1';
            $leaveRequest->deleted_at = \Carbon\Carbon::now();
            $leaveRequest->save();
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

        foreach ($leaveRequests as $leaveRequest) {
            $is_trashed = $leaveRequest->is_trashed;

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

                        $leaveBalance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                            ->where('leavetype_id', $leaveRequest->leavetype_id)
                            ->first();

                        if ($leaveBalance) {
                            $leaveBalance->balance_amount = (float)$leaveBalance->balance_amount + (float)$numberOfDays;
                            $leaveBalance->save();
                        }

                        if ($hasIsDeducted) {
                            $leaveRequest->is_deducted = false;
                        }
                    }
                }

                $leaveRequest->is_trashed = '1';
                $leaveRequest->deleted_at = \Carbon\Carbon::now();
                $leaveRequest->save();
                $softDeleted[] = $leaveRequest->id;
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
