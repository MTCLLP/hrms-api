<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

<<<<<<< HEAD

=======
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveBalance;
>>>>>>> 34f31272c41e5b91d0242003286025ddc8ca58ef
use Modules\Leave\Models\LeaveApproval;
use Modules\Leave\Transformers\LeaveApprovalResource as LeaveApprovalResource;
use Modules\RBAC\Transformers\User as UserResource;



class LeaveApprovalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Determine query parameters
        $isTrashed = $request->boolean('trashed', false); // Defaults to false
        $isPaginated = $request->boolean('paginate', false); // Defaults to false
        $order = $request->get('order', 'desc'); // Defaults to 'desc'


        // Build the query
        $query = QueryBuilder::for(LeaveApproval::class)->where('leaverequest_id', $request->id)
            ->allowedFilters(['start_date', 'end_date', 'isPaidLeave']);

        // Fetch data based on pagination preference
        $leaveApprovals = $isPaginated
            ? $query->paginate(10)->appends($request->query())
            : $query->get();


        return UserResource::collection($leaveApprovals);
    }

    /**
     * Store a newly created resource in storage.
     * @param Setting $Setting
     * @return Renderable
     */
    public function store(Request $request)
    {
        $leaveApprovals = LeaveApproval::create([
            'leaverequest_id' => $request->input('leaverequest_id'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'remarks' => $request->input('remarks'),
            'total_days' => $request->input('total_days'),
            'approver_id' => Auth::user()->employee->id,
            'status' => $request->input('status'),
            'isPaidLeave' => $request->input('isPaidLeave'),
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new LeaveApprovalResource($leaveApprovals);
    }

    /**
     * Show the specified resource.
     */
    public function show(LeaveApproval $leaveApproval)
    {
        return new LeaveApprovalResource($leaveApproval);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeaveApproval $leaveApproval)
    {
        // Retrieve the old status before updating
        $oldStatus = $leaveApproval->status;
        $oldTotalDays = $leaveApproval->total_days;

        // Get the new data
        $data = $request->only([
            'leaverequest_id',
            'start_date',
            'end_date',
            'remarks',
            'total_days',
            'status',
            'isPaidLeave',
        ]);

        // Update the LeaveApproval record
        $leaveApproval->update($data);

        // Fetch the related LeaveRequest
        $leaveRequest = LeaveRequest::find($leaveApproval->leaverequest_id);

        if (!$leaveRequest) {
            return response()->json(['error' => 'Leave Request not found'], 404);
        }

        // Get the employee and leave type
        $employeeId = $leaveRequest->employee_id;
        $leaveTypeId = $leaveRequest->leavetype_id;

        // Fetch the leave balance record
        $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leavetype_id', $leaveTypeId)
            ->first();

        if (!$leaveBalance) {
            return response()->json(['error' => 'Leave Balance not found'], 404);
        }

        // Adjustment Logic
        if (
            in_array($oldStatus, ['Rejected', 'ApprovedWithoutPay']) &&
            in_array($data['status'], ['Approved', 'ConditionalApproved'])
        ) {
            // Deduct total_days from leave balance
            $leaveBalance->balance_amount -= $data['total_days'];
        } elseif (
            in_array($oldStatus, ['Approved', 'ConditionalApproved']) &&
            in_array($data['status'], ['Rejected', 'ApprovedWithoutPay'])
        ) {
            // Restore total_days to leave balance
            $leaveBalance->balance_amount += $oldTotalDays;
        }

        // Ensure balance doesn't go negative
        if ($leaveBalance->balance_amount < 0) {
            return response()->json(['error' => 'Insufficient leave balance'], 400);
        }

        // Save the updated balance
        $leaveBalance->save();

        return new LeaveApprovalResource($leaveApproval);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $leaveApproval = LeaveApproval::findOrFail($id);
        $leaveRequestId = $leaveApproval->leaverequest_id;

<<<<<<< HEAD
        $is_trashed = $leaveApproval->is_trashed;

        if ($is_trashed == 1) {
            $leaveApproval->delete(); // delete country
        } else {
            $leaveApproval->is_trashed = '1';
            $leaveApproval->deleted_at = \Carbon\Carbon::now();
            $leaveApproval->save();
        }

        return response()->json([
            "message" => "LeaveApproval deleted"
=======
        // Check if leave was approved or conditional approved
        if (in_array($leaveApproval->status, ['Approved', 'ConditionalApproved', 'ApprovedWithoutPay'])) {
            // Fetch the related LeaveRequest
            $leaveRequest = LeaveRequest::find($leaveRequestId);

            if (!$leaveRequest) {
                return response()->json(['error' => 'Leave Request not found'], 404);
            }

            // Get the employee and leave type
            $employeeId = $leaveRequest->employee_id;
            $leaveTypeId = $leaveRequest->leavetype_id;

            // Fetch the leave balance record
            $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
                ->where('leavetype_id', $leaveTypeId)
                ->first();

            if (!$leaveBalance) {
                return response()->json(['error' => 'Leave Balance not found'], 404);
            }

            // Restore the leave balance
            $leaveBalance->balance_amount += $leaveApproval->total_days;
            $leaveBalance->save();
        }

        // Delete the leave approval
        $leaveApproval->delete();

        // Check if there are any other LeaveApproval records for this LeaveRequest
        $remainingApprovals = LeaveApproval::where('leaverequest_id', $leaveRequestId)->exists();

        if (!$remainingApprovals) {
            // If no approvals exist, update LeaveRequest status to "Pending"
            LeaveRequest::where('id', $leaveRequestId)->update(['status' => 'Pending']);
        }

        return response()->json([
            "message" => "LeaveApproval deleted, leave balance adjusted, and LeaveRequest status updated if necessary"
>>>>>>> 34f31272c41e5b91d0242003286025ddc8ca58ef
        ], 202);
    }



    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $leaveApprovals = LeaveApproval::where('is_trashed', false)->get();

        return LeaveApprovalResource::collection($leaveApprovals);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(LeaveApproval $leaveApproval, $id)
    {
        $leaveApproval = LeaveApproval::findOrFail($id);

        $leaveApproval->is_trashed = '0';
        $leaveApproval->deleted_at = null;
        $leaveApproval->save();

        return response()->json([
            "message" => "Approval restored successfully"
        ], 202);
    }

    /**
     * Remove multiple specified resources from storage.
     *
     * This method is used to delete multiple companies from the database.
     * If the company is already marked as trashed (`is_trashed` == 1), it will be permanently deleted.
     * Otherwise, it will be soft deleted by setting the `is_trashed` flag to 1 and updating the `deleted_at` timestamp.
     *
     *

     * @param \Illuminate\Http\LeaveApproval $LeaveApproval
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyMultiple(LeaveApproval $leaveApproval)
    {
        $ids = $leaveApproval->ids; // Get the array of IDs from the Setting


        if (empty($ids)) {
            return response()->json([
                "message" => "No Approval IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $leaveApprovals = LeaveApproval::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($leaveApprovals as $leaveApproval) {
            $is_trashed = $leaveApproval->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $leaveApproval->delete();
                $deletedPermanently[] = $leaveApproval->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $leaveApproval->is_trashed = '1';
                $leaveApproval->deleted_at = \Carbon\Carbon::now();
                $leaveApproval->save();
                $softDeleted[] = $leaveApproval->id;
            }
        }


        return response()->json([
            "message" => "Leave Approvals processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }
}
