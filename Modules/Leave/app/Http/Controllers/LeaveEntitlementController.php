<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Models\LeaveEntitlement;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Transformers\LeaveEntitlementResource as LeaveEntitlementResource;

class LeaveEntitlementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user(); // Get the authenticated user

        // Fetch all leave types for consistency
        $leaveTypes = LeaveType::pluck('type_name', 'id'); // Assuming LeaveType model exists

        // Result container
        $result = [];

        if ($user->hasRole('Administrator')) {
            // Admin: fetch all leave entitlements
            $leaveEntitlements = LeaveEntitlement::with(['employee.user', 'leavetype'])
                ->where('is_active', 1)
                ->get()
                ->groupBy('employee_id'); // Group by employee ID

            foreach ($leaveEntitlements as $employeeId => $entitlements) {
                $firstEntitlement = $entitlements->first(); // Use the first record to get employee details
                $employee = $firstEntitlement->employee;

                // Prepare entitlement summary for each leave type
                $leaveSummary = $this->formatEntitlements($entitlements, $leaveTypes);

                $result[] = [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->user->name, // Assuming 'name' exists in users table
                    ],
                    'leave_entitlements' => $leaveSummary,
                ];
            }
        } else {
            // Employee: fetch entitlements for the logged-in employee
            $employee = $user->employee;

            if (!$employee) {
                abort(403, 'Employee record not found');
            }

            $entitlements = LeaveEntitlement::with(['leavetype'])
                ->where('employee_id', $employee->id)
                ->where('is_active', 1)
                ->get();

            // Prepare entitlement summary for each leave type
            $leaveSummary = $this->formatEntitlements($entitlements, $leaveTypes);

            $result[] = [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $user->name,
                ],
                'leave_entitlements' => $leaveSummary,
            ];
        }

        // Return the final JSON result
        return response()->json($result);
    }

    /**
     * Format leave entitlements based on all leave types.
     */
    private function formatEntitlements($entitlements, $leaveTypes)
    {
        $leaveSummary = [];

        // Initialize all leave types with 0
        foreach ($leaveTypes as $typeName) {
            $leaveSummary[$typeName] = 0; // Default value
        }

        // Populate actual values from entitlements
        foreach ($entitlements as $entitlement) {
            $leaveType = $entitlement->leavetype->type_name; // Assuming 'type_name' exists
            $balance = $entitlement->ent_amount;            // Get entitlement amount

            $leaveSummary[$leaveType] = $balance;           // Add or update the value
        }

        return $leaveSummary;
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
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'ent_start_date' => 'required|date',
            'ent_end_date' => 'required|date|after_or_equal:ent_start_date',
            'entitlements' => 'required|array',
            'entitlements.*.leaveType_id' => 'required|exists:leave_types,id',
            'entitlements.*.ent_amount' => 'required|integer|min:0',
        ]);

        $entitlements = $request->input('entitlements');
        // $createdBy = auth()->user()->id;

        $insertData = array_map(function ($entitlement) use ($request) {
            return [
                'leaveType_id' => $entitlement['leaveType_id'],
                'employee_id' => $request->input('employee_id'),
                'ent_amount' => $entitlement['ent_amount'],
                'ent_start_date' => $request->input('ent_start_date'),
                'ent_end_date' => $request->input('ent_end_date'),
                // 'created_by' => $createdBy,
                'is_active' => 1,
                'is_trashed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $entitlements);

        // Bulk insert into the database
        LeaveEntitlement::insert($insertData);

        return response()->json([
            'message' => 'Leave entitlements created successfully',
            'data' => $insertData,
        ], 201);
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
