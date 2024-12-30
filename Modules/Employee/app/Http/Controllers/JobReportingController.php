<?php

namespace Modules\Employee\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Employee\Models\JobReporting;
use Modules\Employee\Transformers\JobReportingResource as JobReportingResource;

class JobReportingController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $jobReporting = JobReporting::trashed(false)->orderBy('created_at','desc')->get();

        return JobReportingResource::collection($jobReporting);
    }

    // /**
    //  * Display a paginated listing of the resource.
    //  * @return Response
    //  */
    // public function paginated()
    // {
    //     $jobReporting = JobReporting::paginate(10);

    //     return JobReportingResource::collection($jobReporting);
    // }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $jobReporting = JobReporting::create([
            'superior_id' => $request->input('superior_id'),
            'subordinate_id' => $request->input('subordinate_id'),
            'reporting_method_id' => $request->input('reporting_method'),
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new JobReportingResource($jobReporting);
    }

    public function addSuperior(Request $request)
    {
        if($request->input('employees') == $request->input('subordinateId')){
            return response()->json(['message' => 'Subordinate and Superior cannot be the same.'], 422);
        }

        $jobReporting = JobReporting::create([
            'superior_id' => $request->input('superior'),
            'subordinate_id' => $request->input('subordinateId'),//Current logged in user will be subordinate
            'reporting_method_id' => $request->input('reporting_methods'),
            'created_by' => auth()->user()->id,
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new JobReportingResource($jobReporting);
    }

    public function addSubordinate(Request $request)
    {
        if($request->input('employees') == $request->input('superiorId')){
            return response()->json(['message' => 'Subordinate and Superior cannot be the same.'], 422);
        }

        $jobReporting = JobReporting::create([
            'superior_id' => $request->input('superiorId'),
            'subordinate_id' => $request->input('subordinate'),
            'reporting_method_id' => $request->input('reporting_methods'),
            'created_by' => auth()->user()->id,
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new JobReportingResource($jobReporting);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(JobReporting $jobReporting)
    {
        return new JobReportingResource($jobReporting);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, JobReporting $jobReporting)
    {
        $jobReporting->subordinate_id = $request->input('subordinate_id');
        $jobReporting->superior_id = $request->input('superior_id');
        $jobReporting->reporting_method = $request->input('reporting_method');
        $jobReporting->is_active = $request->input('is_active');
        $jobReporting->is_trashed = $request->input('is_trashed');

        $jobReporting->save();

        return new JobReportingResource($jobReporting);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $jobReporting = JobReporting::findOrFail($id);

		$is_trashed = $jobReporting->is_trashed;

		if($is_trashed == 1) {
			$jobReporting->delete(); // delete country
		}
		else{
            $jobReporting->is_trashed = '1';
            $jobReporting->deleted_at = \Carbon\Carbon::now();
            $jobReporting->save();
        }

		return response()->json([
			"message" => "Job Title deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $jobReporting = JobReporting::ordered('desc')->trashed(true)->get();

        return JobReportingResource::collection($jobReporting);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $jobReporting = JobReporting::findOrFail($id);

        $jobReporting->is_trashed = '0';
        $jobReporting->deleted_at = null;
        $jobReporting->save();

		return response()->json([
			"message" => "Job Title restored successfully"
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
                "message" => "No Job Reporting IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $jobReportings = JobReporting::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($jobReportings as $jobReporting) {
            $is_trashed = $jobReporting->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $jobReporting->delete();
                $deletedPermanently[] = $jobReporting->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $jobReporting->is_trashed = '1';
                $jobReporting->deleted_at = \Carbon\Carbon::now();
                $jobReporting->save();
                $softDeleted[] = $jobReporting->id;
            }
        }


        return response()->json([
            "message" => "Job Reportings processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
