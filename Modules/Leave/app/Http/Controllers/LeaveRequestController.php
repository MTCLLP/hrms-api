<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Mail\NewLeaveRequestNotification;
use Modules\Leave\Mail\LeaveApprovalNotification;
use Modules\Leave\Mail\LeaveRejectionNotification;
use Illuminate\Support\Facades\Mail;

use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\LeaveEntitlement;
use Modules\Leave\Models\LeaveBalance;
use Modules\Employee\Models\JobReporting;
use Carbon\Carbon;
use Modules\Leave\Transformers\LeaveRequestResource as LeaveRequestResource;

class LeaveRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $leaveRequests = LeaveRequest::where('is_trashed',false)->orderBy('created_at','desc')->get();

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
            return response()->json(['error' => 'Start date and end date cannot be a Saturday or Sunday.'], 400);
        }

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
            // return response()->json([
            //     'status' => false,
            //     'message' => 'Leave request overlaps with an existing request.',
            //     'errors' => ['' => ['Invalid email or password']]
            // ], 422);
            return response()->json(['message' => 'Leave request overlaps with an existing request.'], 422);
        }


        // Calculate the number of days (inclusive)
        // Determine if this is a half-day leave
        $isHalfDay = $request->input('is_half_day', false); // Defaults to false if not provided


        if ($isHalfDay) {
            // Half-day logic: Only allow if start_date equals end_date
            if (!$startDate->equalTo($endDate)) {
                return response()->json(['error' => 'Half-day leave can only be for a single day.'], 400);
            }
            $numberOfDays = 0.5; // Half-day counts as 0.5 day
        } else {
            // Calculate the number of full days (inclusive)
            $numberOfDays = (int)($startDate->diffInDays($endDate)) + 1;
        }

        $getLeaveEntitlement = (int)LeaveEntitlement::where('employee_id',$employeeId)->where('leaveType_id',$request->input('selectedLeaveType'))->pluck('ent_amount')->first();

        $getLeaveBalance = (int)LeaveBalance::where('employee_id',$employeeId)->where('leavetype_id',$request->input('selectedLeaveType'))->pluck('balance_amount')->first();

        if ($numberOfDays > $getLeaveEntitlement) {
            return response()->json(['error' => 'Leave days exceed entitlement!'], 400);
        } elseif ($numberOfDays > $getLeaveBalance) {
            return response()->json(['error' => 'Insufficient leave balance!'], 400);
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

            // Notify all superiors
            $superiors = $employee->superiors; // Get the superiors collection from the relationship

            foreach ($superiors as $superior) {
                $superiorDetails = $superior->superiorDetails; // Fetch superior details using the relationship

                if ($superiorDetails && $superiorDetails->user && $superiorDetails->user->email) {
                    Mail::to($superiorDetails->user->email)
                        ->send(new NewLeaveRequestNotification($leaveRequests, $employee));
                }
            }


            return new LeaveRequestResource($leaveRequests);
        }


    }

    public function approveLeave(Request $request)
    {
        $leaveId = $request->input('id');
        $leave = LeaveRequest::find($leaveId);

        if (!$leave) {
            return response()->json(['error' => 'Leave request not found.'], 404);
        }

        $startDate = Carbon::parse($leave->start_date);
        $endDate = Carbon::parse($leave->end_date);

        // Determine number of days
        $numberOfDays = $leave->is_half_day ? 0.5 : (int)($startDate->diffInDays($endDate)) + 1;

        $leaveBalance = LeaveBalance::where('employee_id', $leave->employee_id)
            ->where('leavetype_id', $leave->leavetype_id)
            ->first();

        if (!$leaveBalance) {
            return response()->json(['error' => 'Leave balance record not found.'], 404);
        }

        // Check if the balance is sufficient
        if ($leaveBalance->balance_amount < $numberOfDays) {
            return response()->json(['error' => 'Insufficient leave balance.'], 400);
        }

        // Deduct leave balance
        $leaveBalance->balance_amount -= $numberOfDays;
        $leaveBalance->save();

        // Update leave request status
        $leave->status = 'Approved';
        $leave->comments = $request->input('comment');
        $leave->supervised_by = auth()->user()->id;
        $leave->save();

        // Send approval notification
        $supervisor = auth()->user();
        Mail::to($leave->employee->user->email)
            ->send(new LeaveApprovalNotification($leave, $supervisor));

        return response()->json(['message' => 'Leave request approved successfully.'], 200);
    }


    public function rejectLeave(Request $request){
        $leaveId = $request->input('id');
        $leave = LeaveRequest::find($leaveId);
        $leave->status = 'Rejected';
        $leave->comments = $request->input('comment');
        $leave->supervised_by = auth()->user()->id;
        $leave->save();

        // Send approval notification
        $supervisor = auth()->user();
        Mail::to($leave->employee->user->email)
            ->send(new LeaveRejectionNotification($leave, $supervisor));

        return response()->json(['message' => 'Leave request rejected successfully.'], 200);
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
            // return response()->json([
            //     'status' => false,
            //     'message' => 'Leave request overlaps with an existing request.',
            //     'errors' => ['' => ['Invalid email or password']]
            // ], 422);
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
