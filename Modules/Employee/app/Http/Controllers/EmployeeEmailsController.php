<?php

namespace Modules\Employee\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Employee\Models\EmployeeEmail;
use Modules\Employee\Transformers\EmployeeEmailResource as EmployeeEmailResource;

class EmployeeEmailsController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {

        $employeeEmails = EmployeeEmail::where('employee_id',$request->input('employee_id'))->orderBy('created_at','desc')->get();

        return EmployeeEmailResource::collection($employeeEmails);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $employeeEmails = EmployeeEmail::where('employee_id',$request->input('employee_id'))->paginate(10);

        return EmployeeEmailResource::orderBy('created_at','desc')->collection($employeeEmails);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $employeeEmail = EmployeeEmail::create([
            'employee_id' => $request->input('employee_id'),
            'email' => $request->input('email'),
            'email_type' => $request->input('email_type'),
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new EmployeeEmailResource($employeeEmail);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(EmployeeEmail $employeeEmail)
    {
        return new EmployeeEmailResource($employeeEmail);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, EmployeeEmail $employeeEmail)
    {
        $employeeEmail->employee_id = $request->input('employee_id');
        $employeeEmail->email = $request->input('email');
        $employeeEmail->email_type = $request->input('email_type');
        $employeeEmail->is_active = $request->input('is_active');
        $employeeEmail->is_trashed = $request->input('is_trashed');

        $employeeEmail->save();

        return new EmployeeEmailResource($employeeEmail);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $employeeEmail = EmployeeEmail::findOrFail($id);

		$is_trashed = $employeeEmail->is_trashed;

		if($is_trashed == 1) {
			$employeeEmail->delete(); // delete country
		}
		else{
            $employeeEmail->is_trashed = '1';
            $employeeEmail->deleted_at = \Carbon\Carbon::now();
            $employeeEmail->save();
        }

		return response()->json([
			"message" => "Employee Email deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $employeeEmails = EmployeeEmail::ordered('desc')->trashed(true)->get();

        return EmployeeEmailResource::collection($employeeEmails);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $employeeEmail = EmployeeEmail::findOrFail($id);

        $employeeEmail->is_trashed = '0';
        $employeeEmail->deleted_at = null;
        $employeeEmail->save();

		return response()->json([
			"message" => "Employee Email restored successfully"
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
                "message" => "No employee emails IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $employeeEmails = EmployeeEmail::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($employeeEmails as $employeeEmail) {
            $is_trashed = $employeeEmail->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $employeeEmail->delete();
                $deletedPermanently[] = $employeeEmail->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $employeeEmail->is_trashed = '1';
                $employeeEmail->deleted_at = \Carbon\Carbon::now();
                $employeeEmail->save();
                $softDeleted[] = $employeeEmail->id;
            }
        }


        return response()->json([
            "message" => "Emails processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
