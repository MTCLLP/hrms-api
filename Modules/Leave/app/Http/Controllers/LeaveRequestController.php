<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Mail\NewLeaveRequestNotification;
use Modules\Leave\Mail\LeaveApprovalNotification;
use Modules\Leave\Mail\LeaveRejectionNotification;
use Illuminate\Support\Facades\Mail;
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
            $leaveRequests = $leaveRequests->paginate(10);
        } else {
            $leaveRequests = $leaveRequests->get();
        }

        return LeaveRequestResource::collection($leaveRequests);
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
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } elseif ($user->hasRole('Manager')) {
            $managerEmployeeId = $user->employee->id;

            $subordinateIds = JobReporting::where('superior_id', $managerEmployeeId)
                ->where('is_active', true)
                ->pluck('subordinate_id');

            $leaveRequests = LeaveRequest::where('is_trashed', false)
                ->whereIn('employee_id', $subordinateIds->push($managerEmployeeId)) // Add manager's own ID
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } elseif ($user->hasRole('Employee')) {
            $employeeId = $user->employee->id;

            $leaveRequests = LeaveRequest::where('is_trashed', false)
                ->where('employee_id', $employeeId)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            $leaveRequests = LeaveRequest::whereRaw('0 = 1')->paginate(10); // Empty query
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
                'message' => 'Leave cannot start on a weekend',
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

        $getLeaveEntitlement = (int)LeaveEntitlement::where('employee_id',$employeeId)->where('leaveType_id',$request->input('selectedLeaveType'))->pluck('ent_amount')->first();

        $getLeaveBalance = (int)LeaveBalance::where('employee_id',$employeeId)->where('leavetype_id',$request->input('selectedLeaveType'))->pluck('balance_amount')->first();

        if($numberOfDays > 5){
            return response()->json([
                'status' => false,
                'message' => 'Too many leaves',
                'errors' => ['Error' => ['You can only take 5 days of leave at once!.']]
            ], 400);
        }
        elseif ($numberOfDays > $getLeaveEntitlement) {
            return response()->json([
                'status' => false,
                'message' => 'Leave days exceed entitlement',
                'errors' => ['Error' => ['Leave days exceed entitlement!.']]
            ], 400);
        } elseif ($numberOfDays > $getLeaveBalance) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient leave balance',
                'errors' => ['Error' => ['Insufficient leave balance.']]
            ], 400);
        } else {
            $leaveRequests = LeaveRequest::create([
                'employee_id' => $employeeId,
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'leavetype_id' => $request->input('selectedLeaveType'),
                'is_half_day' => $request->input('is_half_day'),
                'leave_description' => $request->input('leave_description'),
                // 'status' => $request->input('status'),
                // 'comments' => $request->input('comments'),
                'created_by' => auth()->user()->id,
                'is_active' => 1,
                'is_trashed' => 0,

            ]);
            if (!(auth()->user()->id == 21 || auth()->user()->id == 22)) {
                // Notify all superiors
                $superiors = $employee->superiors; // Get the superiors collection from the relationship

                foreach ($superiors as $superior) {
                    $superiorDetails = $superior->superiorDetails; // Fetch superior details using the relationship

                    if ($superiorDetails && $superiorDetails->user && $superiorDetails->user->email) {
                        Mail::to($superiorDetails->user->email)
                            ->send(new NewLeaveRequestNotification($leaveRequests, $employee));
                    }
                }
            }

            return new LeaveRequestResource($leaveRequests);
        }
    }

    /**
     * Store a newly created resource in storage.
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

        if ($isPaidLeave) {
            $remainingDays = $numberOfDays;
            $deductedLeaveTypes = [];

            // Check if requested leave type has sufficient balance
            $primaryLeaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
                ->where('leavetype_id', $leave->leavetype_id)
                ->first();

            if ($primaryLeaveBalance && $primaryLeaveBalance->balance_amount >= $remainingDays) {
                // Deduct from the requested leave type directly
                $primaryLeaveBalance->balance_amount -= $remainingDays;
                $primaryLeaveBalance->save();
                $deductedLeaveTypes[] = [
                    'leave_type' => $leave->leaveType->type_name,
                    'days_deducted' => $remainingDays,
                ];
                $remainingDays = 0;
            } else {
                // If insufficient balance, try other leave types in priority order
                $leaveTypesOrder = ['Earned', 'Casual', 'Restricted', 'Sick'];

                foreach ($leaveTypesOrder as $leaveTypeName) {
                    if ($remainingDays <= 0) {
                        break;
                    }

                    $leaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
                        ->whereHas('leaveType', function ($query) use ($leaveTypeName) {
                            $query->where('type_name', $leaveTypeName);
                        })
                        ->first();

                    if ($leaveBalance && $leaveBalance->balance_amount > 0) {
                        $deductibleDays = min($leaveBalance->balance_amount, $remainingDays);
                        $leaveBalance->balance_amount -= $deductibleDays;
                        $leaveBalance->save();

                        $deductedLeaveTypes[] = [
                            'leave_type' => $leaveTypeName,
                            'days_deducted' => $deductibleDays,
                        ];

                        $remainingDays -= $deductibleDays;
                    }
                }
            }

            // If leave balance is still insufficient
            if ($remainingDays > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient balance',
                    'errors' => ['Error' => ['Insufficient leave balance.']]
                ], 400);
            }

            // Log deducted leave details (optional)
            \Log::info('Leave deducted:', $deductedLeaveTypes);
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
                $leave->status = 'Approved';
                break;
            default:
                // Handle default case if necessary
                break;
        }

        $leave->supervised_by = auth()->user()->id;
        $leave->save();

        // Create approval record
        LeaveApproval::create([
            'leaverequest_id' => $leave->id,
            'approver_id' => auth()->user()->employee->id,
            'start_date' => $leave->start_date,
            'end_date' => $leave->end_date,
            'total_days' => $numberOfDays,
            'isPaidLeave' => $isPaidLeave,
            'status' => $leave->status,
            'remarks' => $request->input('comment'),
        ]);

        // Send notification
        Mail::to($leave->employee->user->email)
            ->send(new LeaveApprovalNotification($leave, $approver));

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

        $approver = auth()->user();

        // If the leave was previously approved, reverse the leave balance adjustments
        if ($leave->status === 'Approved' || $leave->status === 'PartialApproved') {
            // Get the associated leave approvals for the leave request
            $leaveApprovals = LeaveApproval::where('leaverequest_id', $leave->id)
                ->whereIn('status', ['Approved', 'PartialApproved'])
                ->get();

            foreach ($leaveApprovals as $approval) {
                $startDate = Carbon::parse($approval->start_date);
                $endDate = Carbon::parse($approval->end_date);
                $numberOfDays = $approval->total_days;
                $leaveTypeId = $leave->leavetype_id; // Assuming the leave type for the request is the same

                // Find the corresponding leave balance
                $leaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
                    ->where('leavetype_id', $leaveTypeId)
                    ->first();

                if ($leaveBalance) {
                    // Restore the deducted leave balance
                    $leaveBalance->balance_amount += $numberOfDays;
                    $leaveBalance->save();
                }

                // Optionally, if partial approval, adjust the other leave types similarly
                if ($approval->status === 'PartialApproved') {
                    // Handle partial leave type deductions (if applicable)
                    $partialLeaveTypes = json_decode($approval->remarks, true); // assuming partial leave types are stored in remarks

                    foreach ($partialLeaveTypes as $leaveTypeData) {
                        $leaveTypeName = $leaveTypeData['leave_type'];
                        $daysDeducted = $leaveTypeData['days_deducted'];

                        // Find the corresponding leave balance
                        $partialLeaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
                            ->whereHas('leaveType', function ($query) use ($leaveTypeName) {
                                $query->where('type_name', $leaveTypeName);
                            })
                            ->first();

                        if ($partialLeaveBalance) {
                            // Restore the deducted partial leave balance
                            $partialLeaveBalance->balance_amount += $daysDeducted;
                            $partialLeaveBalance->save();
                        }
                    }
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
            'approver_id' => auth()->user()->employee->id,
            'start_date' => $leave->start_date,
            'end_date' => $leave->end_date,
            'total_days' => 0, // No days deducted for rejection
            'isPaidLeave' => false,
            'status' => 'Rejected',
            'remarks' => $request->input('comment'),
        ]);

        // Notify the employee
        Mail::to($leave->employee->user->email)
            ->send(new LeaveRejectionNotification($leave, $approver));

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

        foreach ($partialApprovals as $approval) {
            $startDate = Carbon::parse($approval['start_date']);
            $endDate = Carbon::parse($approval['end_date']);
            $status = $approval['status'];
            $numberOfDays = $startDate->diffInDays($endDate) + 1;

            // Determine if it's a paid leave
            $isPaidLeave = ($status !== 'ApprovedWithoutPay' && $status !== 'Rejected');

            if ($isPaidLeave) {
                $remainingDays = $numberOfDays;
                $deductedLeaveTypes = [];

                // Check if requested leave type has sufficient balance
                $primaryLeaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
                    ->where('leavetype_id', $leave->leavetype_id)
                    ->first();

                if ($primaryLeaveBalance && $primaryLeaveBalance->balance_amount >= $remainingDays) {
                    // Deduct from the applied leave type
                    $primaryLeaveBalance->balance_amount -= $remainingDays;
                    $primaryLeaveBalance->save();
                    $deductedLeaveTypes[] = [
                        'leave_type' => $leave->leaveType->type_name,
                        'days_deducted' => $remainingDays,
                    ];
                    $remainingDays = 0;
                } else {
                    // If insufficient balance, try other leave types
                    $leaveTypesOrder = ['Earned Leave', 'Casual Leave', 'Restricted Holiday', 'Sick Leave'];

                    foreach ($leaveTypesOrder as $leaveTypeName) {
                        if ($remainingDays <= 0) {
                            break;
                        }

                        $leaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
                            ->whereHas('leaveType', function ($query) use ($leaveTypeName) {
                                $query->where('type_name', $leaveTypeName);
                            })
                            ->first();

                        if ($leaveBalance && $leaveBalance->balance_amount > 0) {
                            $deductibleDays = min($leaveBalance->balance_amount, $remainingDays);
                            $leaveBalance->balance_amount -= $deductibleDays;
                            $leaveBalance->save();

                            $deductedLeaveTypes[] = [
                                'leave_type' => $leaveTypeName,
                                'days_deducted' => $deductibleDays,
                            ];

                            $remainingDays -= $deductibleDays;
                        }
                    }
                }

                // If leave balance is still insufficient, reject this approval
                if ($remainingDays > 0) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Insufficient leave balance',
                        'errors' => ['Error' => ['Insufficient leave balance for partial approval.']]
                    ], 400);

                }

                // Log deducted leave details (optional)
                \Log::info('Partial Leave deducted:', $deductedLeaveTypes);
            }

            // Update leave status

            $leave->status = 'Partially Approved';

            $leave->supervised_by = auth()->user()->id;
            $leave->save();

            // Create approval record
            LeaveApproval::create([
                'leaverequest_id' => $leave->id,
                'approver_id' => auth()->user()->employee->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $numberOfDays,
                'isPaidLeave' => $isPaidLeave,
                'status' => $status,
                'remarks' => $request->input('comment'),
            ]);
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
        $overlap = LeaveRequest::where('employee_id', $employeeId)
            ->where('is_active', 1) // Only active leaves
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


        // Calculate the number of days (inclusive)
        $numberOfDays = (int)($startDate->diffInDays($endDate)) + 1;

        $getLeaveEntitlement = (int)LeaveEntitlement::where('employee_id',$employeeId)->where('leaveType_id',$request->input('selectedLeaveType'))->pluck('ent_amount')->first();

        $getLeaveBalance = (int)LeaveBalance::where('employee_id',$employeeId)->where('leavetype_id',$request->input('selectedLeaveType'))->pluck('balance_amount')->first();

        if ($numberOfDays > $getLeaveEntitlement) {
            return response()->json(['error' => 'Leave days exceed entitlement!'], 400);
        } elseif ($numberOfDays > $getLeaveBalance) {
            return response()->json(['error' => 'Insufficient leave balance!'], 400);
        } else {

            $leaveRequest->employee_id = $request->input('employee_id');
            $leaveRequest->leavetype_id = $request->input('leavetype_id');
            $leaveRequest->start_date = $request->input('start_date');
            $leaveRequest->end_date = $request->input('end_date');
            $leaveRequest->status = $request->input('status');
            $leaveRequest->comments = $request->input('comments');
            $leaveRequest->leave_description = $request->input('leave_description');
            $leaveRequest->is_active = $request->input('is_active');
            $leaveRequest->is_trashed = $request->input('is_trashed');
            $leaveRequest->created_by = auth()->user()->id;
            $leaveRequest->save();
        }
        return new LeaveRequestResource($leaveRequest);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

		$is_trashed = $leaveRequest->is_trashed;

		if($is_trashed == 1) {
			$leaveRequest->delete(); // delete country
		}
		else{
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
        $leaveRequests = LeaveRequest::where('is_trashed',true)->get();

        return LeaveRequestResource::collection($leaveRequests);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

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
     * This method is used to delete multiple records from the database.
     * If the record is already marked as trashed (`is_trashed` == 1), it will be permanently deleted.
     * Otherwise, it will be soft deleted by setting the `is_trashed` flag to 1 and updating the `deleted_at` timestamp.
     *
     *

     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids; // Get the array of IDs from the request


        if (empty($ids)) {
            return response()->json([
                "message" => "No department IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $leaveRequests = LeaveRequest::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($leaveRequests as $leaveRequest) {
            $is_trashed = $leaveRequest->is_trashed;

            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $leaveRequest->delete();
                $deletedPermanently[] = $leaveRequest->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
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
}
