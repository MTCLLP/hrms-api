<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Models\LeaveEntitlement;
use Modules\Leave\Transformers\LeaveEntitlementResource as LeaveEntitlementResource;

class LeaveEntitlementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user(); // Get the authenticated user

        if ($user->hasRole('Administrator')) {
            // Admin: fetch all leave entitlements
            $leaveEntitlements = LeaveEntitlement::with(['employee.user'])
                ->where('is_active', 1)
                ->get() // Retrieve all results (not paginated initially)
                ->groupBy('employee_id'); // Group by employee ID
        } else {
            // Employee: fetch leave entitlements for the specific employee
            $employee = $user->employee; // Get the employee record associated with the user

            if (!$employee) {
                abort(403, 'Employee record not found');
            }

            $leaveEntitlements = LeaveEntitlement::where('employee_id', $employee->id)
                ->where('is_active', 1)
                ->paginate(10); // Paginate for employees
        }

        // If Admin, handle grouped results manually
        $result = [];
        if ($user->hasRole('Administrator')) {
            foreach ($leaveEntitlements as $employeeId => $entitlements) {
                $firstEntitlement = $entitlements->first(); // Get first entitlement for employee details

                // Prepare entitlement types and values
                $leaveSummary = $this->formatEntitlements($entitlements);

                $result[] = array_merge([
                    'employee' => $firstEntitlement->employee, // Use the first record to get employee details
                ], $leaveSummary);
            }

            return response()->json($result); // Return grouped results
        }

        // For employees, format and return directly paginated results
        $result = [];
        foreach ($leaveEntitlements as $entitlement) {
            $result[] = array_merge([
                'employee' => $entitlement->employee,
            ], $this->formatEntitlements([$entitlement]));
        }

        return response()->json($result);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $user = auth()->user(); // Get the authenticated user

        if ($user->hasRole('Administrator')) {
            // Admin: fetch all leave entitlements
            $leaveEntitlements = LeaveEntitlement::with(['employee.user'])
                ->where('is_active', 1)
                ->get() // Retrieve all results (not paginated initially)
                ->groupBy('employee_id'); // Group by employee ID
        } else {
            // Employee: fetch leave entitlements for the specific employee
            $employee = $user->employee; // Get the employee record associated with the user

            if (!$employee) {
                abort(403, 'Employee record not found');
            }

            $leaveEntitlements = LeaveEntitlement::where('employee_id', $employee->id)
                ->where('is_active', 1)
                ->paginate(10); // Paginate for employees
        }

        // If Admin, handle grouped results manually
        $result = [];
        if ($user->hasRole('Administrator')) {
            foreach ($leaveEntitlements as $employeeId => $entitlements) {
                $firstEntitlement = $entitlements->first(); // Get first entitlement for employee details

                // Prepare entitlement types and values
                $leaveSummary = $this->formatEntitlements($entitlements);

                $result[] = array_merge([
                    'employee' => $firstEntitlement->employee, // Use the first record to get employee details
                ], $leaveSummary);
            }

            return response()->json($result); // Return grouped results
        }

        // For employees, format and return directly paginated results
        $result = [];
        foreach ($leaveEntitlements as $entitlement) {
            $result[] = array_merge([
                'employee' => $entitlement->employee,
            ], $this->formatEntitlements([$entitlement]));
        }

        return response()->json($result);
    }

    /**
     * Format entitlements for output
     */
    private function formatEntitlements($entitlements)
    {
        $leaveSummary = [];

        foreach ($entitlements as $entitlement) {

            $leaveType = $entitlement->leavetype->type_name; // Assuming there's a 'leave_type' column
            $balance = $entitlement->ent_amount;     // Assuming 'balance' holds leave value
            $id = $entitlement->id;


            $leaveSummary[$leaveType] = $balance; // Dynamically add leave type and value
        }

        return $leaveSummary;
    }



    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $leaveEntitlements = LeaveEntitlement::create([
            'leaveType_id' => $request->input('leaveType_id'),
            'employee_id' => $request->input('employee_id'),
            'ent_amount' => $request->input('ent_amount'),
            'ent_start_date' => $request->input('ent_start_date'),
            'ent_end_date' => $request->input('ent_end_date'),
            'created_by' => auth()->user()->id,
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new LeaveEntitlementResource($leaveEntitlements);
    }

    /**
     * Show the specified resource.
     */
    public function show(LeaveEntitlement $leaveEntitlement)
    {
        return new LeaveEntitlementResource($leaveEntitlement);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeaveEntitlement $leaveEntitlement)
    {
        $leaveEntitlement->leaveType_id = $request->input('leaveType_id');
        $leaveEntitlement->employee_id = $request->input('employee_id');
        $leaveEntitlement->ent_amount = $request->input('ent_amount');
        $leaveEntitlement->ent_start_date = $request->input('ent_start_date');
        $leaveEntitlement->ent_end_date = $request->input('ent_end_date');
        $leaveEntitlement->is_active = $request->input('is_active');
        $leaveEntitlement->is_trashed = $request->input('is_trashed');
        $leaveEntitlement->created_by = auth()->user()->id;
        $leaveEntitlement->save();

        return new LeaveEntitlementResource($leaveEntitlement);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $leaveEntitlement = LeaveEntitlement::findOrFail($id);

		$is_trashed = $leaveEntitlement->is_trashed;

		if($is_trashed == 1) {
			$leaveEntitlement->delete(); // delete country
		}
		else{
            $leaveEntitlement->is_trashed = '1';
            $leaveEntitlement->deleted_at = \Carbon\Carbon::now();
            $leaveEntitlement->save();
        }

		return response()->json([
			"message" => "Leave Entitlement deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $leaveEntitlements = LeaveEntitlement::where('is_trashed',false)->get();

        return LeaveEntitlementResource::collection($leaveEntitlements);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $leaveEntitlement = LeaveEntitlement::findOrFail($id);

        $leaveEntitlement->is_trashed = '0';
        $leaveEntitlement->deleted_at = null;
        $leaveEntitlement->save();

		return response()->json([
			"message" => "Leave Entitlement restored successfully"
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
        $leaveEntitlements = LeaveEntitlement::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($leaveEntitlements as $leaveEntitlement) {
            $is_trashed = $leaveEntitlement->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $leaveEntitlement->delete();
                $deletedPermanently[] = $leaveEntitlement->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $leaveEntitlement->is_trashed = '1';
                $leaveEntitlement->deleted_at = \Carbon\Carbon::now();
                $leaveEntitlement->save();
                $softDeleted[] = $leaveEntitlement->id;
            }
        }


        return response()->json([
            "message" => "Leave Entitlement processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }
}
