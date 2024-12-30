<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Transformers\LeaveTypeResource as LeaveTypeResource;

class LeaveTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $leaveTypes = LeaveType::where('is_trashed',false)->orderBy('created_at','desc')->get();

        return LeaveTypeResource::collection($leaveTypes);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $leaveTypes = LeaveType::paginate(10);

        return LeaveTypeResource::collection($leaveTypes);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $leaveTypes = LeaveType::create([
            'type_name' => $request->input('type_name'),
            'description' => $request->input('description'),
            'leave_count' => $request->input('leave_count'),
            'created_by' => auth()->user()->id,
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new LeaveTypeResource($leaveTypes);
    }

    /**
     * Show the specified resource.
     */
    public function show(LeaveType $leaveType)
    {
        return new LeaveTypeResource($leaveType);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeaveType $leaveType)
    {
        $leaveType->type_name = $request->input('type_name');
        $leaveType->description = $request->input('description');
        $leaveType->leave_count = $request->input('leave_count');
        $leaveType->is_active = $request->input('is_active');
        $leaveType->is_trashed = $request->input('is_trashed');
        $leaveType->created_by = auth()->user()->id;
        $leaveType->save();

        return new LeaveTypeResource($leaveType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $leaveType = LeaveType::findOrFail($id);

		$is_trashed = $leaveType->is_trashed;

		if($is_trashed == 1) {
			$leaveType->delete(); // delete country
		}
		else{
            $leaveType->is_trashed = '1';
            $leaveType->deleted_at = \Carbon\Carbon::now();
            $leaveType->save();
        }

		return response()->json([
			"message" => "Leave Type deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $leaveTypes = LeaveType::where('is_trashed',false)->get();

        return LeaveTypeResource::collection($leaveTypes);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $leaveType = LeaveType::findOrFail($id);

        $leaveType->is_trashed = '0';
        $leaveType->deleted_at = null;
        $leaveType->save();

		return response()->json([
			"message" => "Leave Type restored successfully"
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
        $leaveTypes = LeaveType::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($leaveTypes as $leaveType) {
            $is_trashed = $leaveType->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $leaveType->delete();
                $deletedPermanently[] = $leaveType->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $leaveType->is_trashed = '1';
                $leaveType->deleted_at = \Carbon\Carbon::now();
                $leaveType->save();
                $softDeleted[] = $leaveType->id;
            }
        }


        return response()->json([
            "message" => "Leave Type processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }
}
