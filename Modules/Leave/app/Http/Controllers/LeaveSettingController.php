<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Setting;
use Modules\Leave\Models\LeaveSetting;
use Modules\Leave\Transformers\LeaveSettingResource as LeaveSettingResource;

class LeaveSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $leaveSettings = LeaveSetting::where('is_trashed',false)->orderBy('created_at','desc')->get();

        return LeaveSettingResource::collection($leaveSettings);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $leaveSettings = LeaveSetting::paginate(10);

        return LeaveSettingResource::collection($leaveSettings);
    }

    /**
     * Store a newly created resource in storage.
     * @param Setting $Setting
     * @return Renderable
     */
    public function store(Request $request)
    {
        $leaveSettings = LeaveSetting::create([
            'leaveType_id' => $request->input('leaveType_id'),
            'accrual_method' => $request->input('accrual_method'),
            'accrual_rate' => $request->input('accrual_rate'),
            'maximum_accrual' => $request->input('maximum_accrual'),
            'allow_negative_bal' => $request->input('allow_negative_bal'),
            'created_by' => auth()->user()->id,
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new LeaveSettingResource($leaveSettings);
    }

    /**
     * Show the specified resource.
     */
    public function show(LeaveSetting $leaveSetting)
    {
        return new LeaveSettingResource($leaveSetting);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeaveSetting $leaveSetting)
    {
        $leaveSetting->leaveType_id = $request->input('leaveType_id');
        $leaveSetting->accrual_method = $request->input('accrual_method');
        $leaveSetting->accrual_rate = $request->input('accrual_rate');
        $leaveSetting->maximum_accrual = $request->input('maximum_accrual');
        $leaveSetting->allow_negative_bal = $request->input('allow_negative_bal');
        $leaveSetting->is_active = $request->input('is_active');
        $leaveSetting->is_trashed = $request->input('is_trashed');
        $leaveSetting->created_by = auth()->user()->id;
        $leaveSetting->save();

        return new LeaveSettingResource($leaveSetting);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $leaveSetting = LeaveSetting::findOrFail($id);

		$is_trashed = $leaveSetting->is_trashed;

		if($is_trashed == 1) {
			$leaveSetting->delete(); // delete country
		}
		else{
            $leaveSetting->is_trashed = '1';
            $leaveSetting->deleted_at = \Carbon\Carbon::now();
            $leaveSetting->save();
        }

		return response()->json([
			"message" => "Leave Setting deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $leaveSettings = LeaveSetting::where('is_trashed',false)->get();

        return LeaveSettingResource::collection($leaveSettings);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Setting $Setting, $id)
    {
        $leaveSetting = LeaveSetting::findOrFail($id);

        $leaveSetting->is_trashed = '0';
        $leaveSetting->deleted_at = null;
        $leaveSetting->save();

		return response()->json([
			"message" => "Leave Setting restored successfully"
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

     * @param \Illuminate\Http\Setting $Setting
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyMultiple(Setting $Setting)
    {
        $ids = $Setting->ids; // Get the array of IDs from the Setting


        if (empty($ids)) {
            return response()->json([
                "message" => "No department IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $leaveSettings = LeaveSetting::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($leaveSettings as $leaveSetting) {
            $is_trashed = $leaveSetting->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $leaveSetting->delete();
                $deletedPermanently[] = $leaveSetting->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $leaveSetting->is_trashed = '1';
                $leaveSetting->deleted_at = \Carbon\Carbon::now();
                $leaveSetting->save();
                $softDeleted[] = $leaveSetting->id;
            }
        }


        return response()->json([
            "message" => "Leave Setting processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }
}
