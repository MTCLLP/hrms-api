<?php

namespace Modules\Employee\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Employee\Models\EmployeeAddress;
use Modules\Employee\Http\Requests\EmployeeAddressRequest;
use Modules\Employee\Transformers\EmployeeAddressResource as EmployeeAddressResource;

class EmployeeAddressController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $employeeAddresss = EmployeeAddress::where('employee_id',$request->input('employee_id'))->orderBy('created_at','desc')->get();

        return EmployeeAddressResource::collection($employeeAddresss);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $employeeAddresss = EmployeeAddress::where('employee_id',$request->input('employee_id'))->orderBy('created_at','desc')->paginate(10);

        return EmployeeAddressResource::collection($employeeAddresss);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(EmployeeAddressRequest $request)
    {
        $employeeAddress = EmployeeAddress::create([
            'employee_id' => $request->input('employee_id'),
            'address' => $request->input('address'),
            'address_type' => $request->input('address_type'),
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new EmployeeAddressResource($employeeAddress);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(EmployeeAddress $employeeAddress)
    {
        return new EmployeeAddressResource($employeeAddress);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(EmployeeAddressRequest $request, EmployeeAddress $employeeAddress)
    {
        $employeeAddress->employee_id = $request->input('employee_id');
        $employeeAddress->address = $request->input('address');
        $employeeAddress->address_type = $request->input('address_type');
        $employeeAddress->is_active = $request->input('is_active');
        $employeeAddress->is_trashed = $request->input('is_trashed');

        $employeeAddress->save();

        return new EmployeeAddressResource($employeeAddress);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $employeeAddress = EmployeeAddress::findOrFail($id);

		$is_trashed = $employeeAddress->is_trashed;

		if($is_trashed == 1) {
			$employeeAddress->delete(); // delete country
		}
		else{
            $employeeAddress->is_trashed = '1';
            $employeeAddress->deleted_at = \Carbon\Carbon::now();
            $employeeAddress->save();
        }

		return response()->json([
			"message" => "Employee Address deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $employeeAddresss = EmployeeAddress::ordered('desc')->trashed(true)->get();

        return EmployeeAddressResource::collection($employeeAddresss);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $employeeAddress = EmployeeAddress::findOrFail($id);

        $employeeAddress->is_trashed = '0';
        $employeeAddress->deleted_at = null;
        $employeeAddress->save();

		return response()->json([
			"message" => "Employee Address restored successfully"
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
                "message" => "No employee address IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $employeeAddresss = EmployeeAddress::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($employeeAddresss as $employeeAddress) {
            $is_trashed = $employeeAddress->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $employeeAddress->delete();
                $deletedPermanently[] = $employeeAddress->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $employeeAddress->is_trashed = '1';
                $employeeAddress->deleted_at = \Carbon\Carbon::now();
                $employeeAddress->save();
                $softDeleted[] = $employeeAddress->id;
            }
        }


        return response()->json([
            "message" => "Addresses processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
