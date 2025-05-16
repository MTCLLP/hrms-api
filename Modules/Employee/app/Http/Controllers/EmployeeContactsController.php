<?php

namespace Modules\Employee\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Employee\Models\EmployeeContact;
use Modules\Employee\Http\Requests\EmployeeContactRequest;
use Modules\Employee\Transformers\EmployeeContactResource as EmployeeContactResource;

class EmployeeContactsController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $employeeContacts = EmployeeContact::where('employee_id',$request->input('employee_id'))->orderBy('created_at','desc')->get();

        return EmployeeContactResource::collection($employeeContacts);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $employeeContacts = EmployeeContact::where('employee_id',$request->input('employee_id'))->orderBy('created_at','desc')->paginate(10);

        return EmployeeContactResource::collection($employeeContacts);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(EmployeeContactRequest $request)
    {
        $employeeContact = EmployeeContact::create([
            'employee_id' => $request->input('employee_id'),
            'number' => $request->input('number'),
            'contact_type' => $request->input('contact_type'),
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new EmployeeContactResource($employeeContact);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(EmployeeContact $employeeContact)
    {
        return new EmployeeContactResource($employeeContact);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(EmployeeContactRequest $request, EmployeeContact $employeeContact)
    {
        $employeeContact->employee_id = $request->input('employee_id');
        $employeeContact->number = $request->input('number');
        $employeeContact->contact_type = $request->input('contact_type');
        $employeeContact->is_active = $request->input('is_active');
        $employeeContact->is_trashed = $request->input('is_trashed');

        $employeeContact->save();

        return new EmployeeContactResource($employeeContact);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $employeeContact = EmployeeContact::findOrFail($id);

		$is_trashed = $employeeContact->is_trashed;

		if($is_trashed == 1) {
			$employeeContact->delete(); // delete country
		}
		else{
            $employeeContact->is_trashed = '1';
            $employeeContact->deleted_at = \Carbon\Carbon::now();
            $employeeContact->save();
        }

		return response()->json([
			"message" => "Employee Contact deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $employeeContacts = EmployeeContact::ordered('desc')->trashed(true)->get();

        return EmployeeContactResource::collection($employeeContacts);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $employeeContact = EmployeeContact::findOrFail($id);

        $employeeContact->is_trashed = '0';
        $employeeContact->deleted_at = null;
        $employeeContact->save();

		return response()->json([
			"message" => "Employee Contact restored successfully"
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
                "message" => "No employee contact IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $employeeContacts = EmployeeContact::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($employeeContacts as $employeeContact) {
            $is_trashed = $employeeContact->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $employeeContact->delete();
                $deletedPermanently[] = $employeeContact->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $employeeContact->is_trashed = '1';
                $employeeContact->deleted_at = \Carbon\Carbon::now();
                $employeeContact->save();
                $softDeleted[] = $employeeContact->id;
            }
        }


        return response()->json([
            "message" => "Contacts processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
