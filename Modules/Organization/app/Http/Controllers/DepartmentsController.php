<?php

namespace Modules\Organization\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Organization\Models\Department;
use Modules\Organization\Transformers\DepartmentResource as DepartmentResource;

class DepartmentsController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $departments = Department::where('is_trashed',false)->orderBy('created_at','desc')->get();

        return DepartmentResource::collection($departments);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $departments = Department::paginate(10);

        return DepartmentResource::collection($departments);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $department = Department::create([
            'name' => $request->input('name'),
            'created_by' => auth()->user()->id,
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        if($request->input('branches')){
            $department->branches()->attach($request->input('branches'));
        }

        return new DepartmentResource($department);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(Department $department)
    {
        return new DepartmentResource($department);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, Department $department)
    {
        $department->name = $request->input('name');
        $department->branch_id = $request->input('branch_id');
        $department->is_active = $request->input('is_active');
        $department->is_trashed = $request->input('is_trashed');
        $department->created_by = auth()->user()->id;
        $department->save();

        return new DepartmentResource($department);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $department = Department::findOrFail($id);

		$is_trashed = $department->is_trashed;

		if($is_trashed == 1) {
			$department->delete(); // delete country
		}
		else{
            $department->is_trashed = '1';
            $department->deleted_at = \Carbon\Carbon::now();
            $department->save();
        }

		return response()->json([
			"message" => "Department deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $departments = Department::where('is_trashed',false)->get();

        return DepartmentResource::collection($departments);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $department->is_trashed = '0';
        $department->deleted_at = null;
        $department->save();

		return response()->json([
			"message" => "Department restored successfully"
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
        $departments = Department::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($departments as $department) {
            $is_trashed = $department->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $department->delete();
                $deletedPermanently[] = $department->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $department->is_trashed = '1';
                $department->deleted_at = \Carbon\Carbon::now();
                $department->save();
                $softDeleted[] = $department->id;
            }
        }


        return response()->json([
            "message" => "Department processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
