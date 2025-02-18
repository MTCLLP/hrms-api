<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

use Modules\Leave\Models\LeaveApproval;
use Modules\Leave\Transformers\LeaveApprovalResource as LeaveApprovalResource;

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
        $query = QueryBuilder::for(LeaveApproval::class)->where('leaverequest_id',$request->id)
            ->allowedFilters(['start_date','end_date','isPaidLeave']);

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
            'approver_id' => auth()->user()->employee->id,
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
        $data = $request->only([
            'leaverequest_id',
            'start_date',
            'end_date',
            'remarks',
            'total_days',
            'status',
            'isPaidLeave',
        ]);

        $leaveApproval->update($data);

        return new LeaveApprovalResource($leaveApproval);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $leaveApproval = LeaveApproval::findOrFail($id);

		$is_trashed = $leaveApproval->is_trashed;

		if($is_trashed == 1) {
			$leaveApproval->delete(); // delete country
		}
		else{
            $leaveApproval->is_trashed = '1';
            $leaveApproval->deleted_at = \Carbon\Carbon::now();
            $leaveApproval->save();
        }

		return response()->json([
			"message" => "LeaveApproval deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $leaveApprovals = LeaveApproval::where('is_trashed',false)->get();

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
