<?php

namespace Modules\Employee\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Employee\Models\EmployeeExperience;
use Modules\Employee\Transformers\EmployeeExperienceResource as EmployeeExperienceResource;

class EmployeeExperienceController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {

        $employeeExperiences = EmployeeExperience::where('employee_id',$request->input('employee_id'))->orderBy('created_at','desc')->get();

        return EmployeeExperienceResource::collection($employeeExperiences);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $employeeExperiences = EmployeeExperience::where('employee_id',$request->input('employee_id'))->orderBy('created_at','desc')->paginate(10);

        return EmployeeExperienceResource::collection($employeeExperiences);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $employeeExperience = EmployeeExperience::create([
            'employee_id' => $request->input('employee_id'),
            'company_name' => $request->input('company_name'),
            'designation' => $request->input('designation'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'created_by' => auth()->user()->id,
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new EmployeeExperienceResource($employeeExperience);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(EmployeeExperience $employeeExperience)
    {
        return new EmployeeExperienceResource($employeeExperience);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, EmployeeExperience $employeeExperience)
    {
        $employeeExperience->employee_id = $request->input('employee_id');
        $employeeExperience->company_name = $request->input('company_name');
        $employeeExperience->designation = $request->input('designation');
        $employeeExperience->start_date = $request->input('start_date');
        $employeeExperience->end_date = $request->input('end_date');
        $employeeExperience->is_active = $request->input('is_active');
        $employeeExperience->is_trashed = $request->input('is_trashed');

        $employeeExperience->save();

        return new EmployeeExperienceResource($employeeExperience);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $employeeExperience = EmployeeExperience::findOrFail($id);

		$is_trashed = $employeeExperience->is_trashed;

		if($is_trashed == 1) {
			$employeeExperience->delete(); // delete country
		}
		else{
            $employeeExperience->is_trashed = '1';
            $employeeExperience->deleted_at = \Carbon\Carbon::now();
            $employeeExperience->save();
        }

		return response()->json([
			"message" => "Employee Experience deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $employeeExperiences = EmployeeExperience::ordered('desc')->trashed(true)->get();

        return EmployeeExperienceResource::collection($employeeExperiences);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $employeeExperience = EmployeeExperience::findOrFail($id);

        $employeeExperience->is_trashed = '0';
        $employeeExperience->deleted_at = null;
        $employeeExperience->save();

		return response()->json([
			"message" => "Employee Experience restored successfully"
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
                "message" => "No employee experience IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $employeeExperiences = EmployeeExperience::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($employeeExperiences as $employeeExperience) {
            $is_trashed = $employeeExperience->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $employeeExperience->delete();
                $deletedPermanently[] = $employeeExperience->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $employeeExperience->is_trashed = '1';
                $employeeExperience->deleted_at = \Carbon\Carbon::now();
                $employeeExperience->save();
                $softDeleted[] = $employeeExperience->id;
            }
        }


        return response()->json([
            "message" => "Employee Experience processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
