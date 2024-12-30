<?php

namespace Modules\Employee\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Employee\Models\EmployeeDependant;
use Modules\Employee\Transformers\EmployeeDependantResource as EmployeeDependantResource;

class EmployeeDependantsController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $employeeDependants = EmployeeDependant::where('employee_id',$request->input('employee_id'))->where('is_trashed',false)->orderBy('created_at','desc')->get();

        return EmployeeDependantResource::collection($employeeDependants);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $employeeDependants = EmployeeDependant::where('employee_id',$request->input('employee_id'))->orderBy('created_at','desc')->paginate(10);

        return EmployeeDependantResource::collection($employeeDependants);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $employeeDependant = EmployeeDependant::create([
            'employee_id' => $request->input('employee_id'),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'number' => $request->input('number'),
            'relationship' => $request->input('relationship'),
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new EmployeeDependantResource($employeeDependant);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(EmployeeDependant $employeeDependant)
    {
        return new EmployeeDependantResource($employeeDependant);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, EmployeeDependant $employeeDependant)
    {
        $employeeDependant->employee_id = $request->input('employee_id');
        $employeeDependant->email = $request->input('email');
        $employeeDependant->name = $request->input('name');
        $employeeDependant->number = $request->input('number');
        $employeeDependant->relationship = $request->input('relationship');
        $employeeDependant->is_active = $request->input('is_active');
        $employeeDependant->is_trashed = $request->input('is_trashed');

        $employeeDependant->save();

        return new EmployeeDependantResource($employeeDependant);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $employeeDependant = EmployeeDependant::findOrFail($id);

		$is_trashed = $employeeDependant->is_trashed;

		if($is_trashed == 1) {
			$employeeDependant->delete(); // delete country
		}
		else{
            $employeeDependant->is_trashed = '1';
            $employeeDependant->deleted_at = \Carbon\Carbon::now();
            $employeeDependant->save();
        }

		return response()->json([
			"message" => "Employee Dependancy deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $employeeDependants = EmployeeDependant::where('is_trashed',false)->get();

        return EmployeeDependantResource::collection($employeeDependants);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $employeeDependant = EmployeeDependant::findOrFail($id);

        $employeeDependant->is_trashed = '0';
        $employeeDependant->deleted_at = null;
        $employeeDependant->save();

		return response()->json([
			"message" => "Employee Dependancy restored successfully"
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
                "message" => "No employee dependant IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $employeeDependants = EmployeeDependant::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($employeeDependants as $employeeDependant) {
            $is_trashed = $employeeDependant->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $employeeDependant->delete();
                $deletedPermanently[] = $employeeDependant->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $employeeDependant->is_trashed = '1';
                $employeeDependant->deleted_at = \Carbon\Carbon::now();
                $employeeDependant->save();
                $softDeleted[] = $employeeDependant->id;
            }
        }


        return response()->json([
            "message" => "Dependants processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
