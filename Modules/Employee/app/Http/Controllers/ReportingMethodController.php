<?php

namespace Modules\Employee\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Employee\Models\ReportingMethod;
use Modules\Employee\Transformers\ReportingMethodResource as ReportingMethodResource;

class ReportingMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $reportingMethods = ReportingMethod::where('is_trashed',false)->orderBy('created_at','desc')->get();


        return ReportingMethodResource::collection($reportingMethods);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $reportingMethods = ReportingMethod::orderBy('created_at','desc')->paginate(10);

        return ReportingMethodResource::collection($reportingMethods);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $reportingMethod = ReportingMethod::create([
            'method' => $request->input('method'),
            'is_active' => 1,
            'is_trashed' => 0,
            'created_by' => auth()->user()->id

        ]);

        return new ReportingMethodResource($reportingMethod);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(ReportingMethod $reportingMethod)
    {
        return new ReportingMethodResource($reportingMethod);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, ReportingMethod $reportingMethod)
    {
        $reportingMethod->method = $request->input('method');
        $reportingMethod->is_active = $request->input('is_active');
        $reportingMethod->is_trashed = $request->input('is_trashed');


        $reportingMethod->save();

        return new ReportingMethodResource($reportingMethod);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $reportingMethod = ReportingMethod::findOrFail($id);

		$is_trashed = $reportingMethod->is_trashed;

		if($is_trashed == 1) {
			$reportingMethod->delete(); // delete country
		}
		else{
            $reportingMethod->is_trashed = '1';
            $reportingMethod->deleted_at = \Carbon\Carbon::now();
            $reportingMethod->save();
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
        $reportingMethods = ReportingMethod::ordered('desc')->get();

        return ReportingMethodResource::collection($reportingMethods);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $reportingMethod = ReportingMethod::findOrFail($id);

        $reportingMethod->is_trashed = '0';
        $reportingMethod->deleted_at = null;
        $reportingMethod->save();

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
                "message" => "No reporting method IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $reportingMethods = ReportingMethod::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($reportingMethods as $reportingMethod) {
            $is_trashed = $reportingMethod->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $reportingMethod->delete();
                $deletedPermanently[] = $reportingMethod->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $reportingMethod->is_trashed = '1';
                $reportingMethod->deleted_at = \Carbon\Carbon::now();
                $reportingMethod->save();
                $softDeleted[] = $reportingMethod->id;
            }
        }


        return response()->json([
            "message" => "Reporting Methods processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
