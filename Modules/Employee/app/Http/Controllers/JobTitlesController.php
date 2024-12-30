<?php

namespace Modules\Employee\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Modules\Employee\Models\JobTitle;
use Modules\Employee\Transformers\JobTitleResource as JobTitleResource;

class JobTitlesController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $jobTitles = JobTitle::where('is_trashed',false)->orderBy('created_at','desc')->get();

        return JobTitleResource::collection($jobTitles);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $jobTitles = JobTitle::where('is_trashed',false)->orderBy('created_at','desc')->paginate(10);

        return JobTitleResource::collection($jobTitles);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $jobTitle = JobTitle::create([
            'title_name' => $request->input('title_name'),
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new JobTitleResource($jobTitle);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(JobTitle $jobTitle)
    {
        return new JobTitleResource($jobTitle);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, jobTitle $jobTitle)
    {
        $jobTitle->title_name = $request->input('title_name');
        $jobTitle->is_active = $request->input('is_active');
        $jobTitle->is_trashed = $request->input('is_trashed');

        $jobTitle->save();

        return new jobTitleResource($jobTitle);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $jobTitle = JobTitle::findOrFail($id);

		$is_trashed = $jobTitle->is_trashed;

		if($is_trashed == 1) {
			$jobTitle->delete(); // delete country
		}
		else{
            $jobTitle->is_trashed = '1';
            $jobTitle->deleted_at = \Carbon\Carbon::now();
            $jobTitle->save();
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
        $jobTitles = JobTitle::ordered('desc')->get();

        return JobTitleResource::collection($jobTitles);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $jobTitle = JobTitle::findOrFail($id);

        $jobTitle->is_trashed = '0';
        $jobTitle->deleted_at = null;
        $jobTitle->save();

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
                "message" => "No job title IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $jobTitles = JobTitle::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($jobTitles as $jobTitle) {
            $is_trashed = $jobTitle->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $jobTitle->delete();
                $deletedPermanently[] = $jobTitle->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $jobTitle->is_trashed = '1';
                $jobTitle->deleted_at = \Carbon\Carbon::now();
                $jobTitle->save();
                $softDeleted[] = $jobTitle->id;
            }
        }


        return response()->json([
            "message" => "Job Titles processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
