<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Transformers\LeaveBalanceResource as LeaveBalanceResource;

class LeaveBalanceController extends Controller
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
            // Admin: fetch all leave balances
            $leaveBalances = LeaveBalance::with(['employee.user', 'leavetype'])
                ->where('is_active', 1)
                ->get()
                ->groupBy('employee_id'); // Group by employee ID

            foreach ($leaveBalances as $employeeId => $balances) {
                $firstBalance = $balances->first(); // Use the first record to get employee details
                $employee = $firstBalance->employee;

                // Prepare entitlement summary for each leave type
                $leaveSummary = $this->formatBalances($balances, $leaveTypes);

                $result[] = [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->user->name, // Assuming 'name' exists in users table
                    ],
                    'leave_balances' => $leaveSummary,
                ];
            }
        } else {
            // Employee: fetch entitlements for the logged-in employee
            $employee = $user->employee;

            if (!$employee) {
                abort(403, 'Employee record not found');
            }

            $balances = LeaveBalance::with(['leavetype'])
                ->where('employee_id', $employee->id)
                ->where('is_active', 1)
                ->get();

            // Prepare entitlement summary for each leave type
            $leaveSummary = $this->formatBalances($balances, $leaveTypes);

            $result[] = [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $user->name,
                ],
                'leave_balances' => $leaveSummary,
            ];
        }

        // Return the final JSON result
        return response()->json($result);
    }

    /**
     * Format leave entitlements based on all leave types.
     */
    private function formatBalances($balances, $leaveTypes)
    {
        $leaveSummary = [];

        // Initialize all leave types with 0
        foreach ($leaveTypes as $typeName) {
            $leaveSummary[$typeName] = 0; // Default value
        }

        // Populate actual values from entitlements
        foreach ($balances as $balance) {
            $leaveType = $balance->leaveType->type_name; // Assuming 'type_name' exists
            $balance = $balance->balance_amount;            // Get entitlement amount

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
            // Admin: fetch all leave balances with pagination
            $leaveBalances = LeaveBalance::with(['employee.user'])
                ->where('is_active', 1)
                ->get() // Retrieve all results for grouping
                ->groupBy('employee_id'); // Group by employee ID

            // Prepare the result for all employees (no pagination for admin)
            $result = [];
            foreach ($leaveBalances as $employeeId => $balances) {
                $firstBalance = $balances->first(); // Get first balance for employee details

                // Prepare balance types and values
                $leaveSummary = $this->formatBalances($balances);

                $result[] = array_merge([
                    'employee' => $firstBalance->employee, // Use the first record to get employee details
                ], $leaveSummary);
            }

            return response()->json($result); // Return grouped results for admin
        } else {
            // Employee: fetch leave balances for the specific employee with pagination
            $employee = $user->employee; // Get the employee record associated with the user

            if (!$employee) {
                abort(403, 'Employee record not found');
            }

            // Paginate the employee's leave balances
            $leaveBalances = LeaveBalance::where('employee_id', $employee->id)
                ->where('is_active', 1)
                ->paginate(10);

            // Format and return paginated results
            $result = [];
            foreach ($leaveBalances as $balance) {
                $result[] = array_merge([
                    'employee' => $balance->employee,
                ], $this->formatBalances([$balance])); // Format each balance entry
            }

            return response()->json($result); // Return paginated results for employee
        }
    }

    public function getLeaveBalance(Request $request)
    {
        $employee = auth()->user()->employee;

        $balances = LeaveBalance::with(['leavetype'])
                ->where('employee_id', $employee->id)
                ->where('is_active', 1)
                ->get();

        return response()->json($balances);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $leaveBalances = LeaveBalance::create([
            'leaveType_id' => $request->input('leaveType_id'),
            'employee_id' => $request->input('employee_id'),
            'balance_amount' => $request->input('balance_amount'),
            'created_by' => auth()->user()->id,
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new LeaveBalanceResource($leaveBalances);
    }

    /**
     * Show the specified resource.
     */
    public function show(LeaveBalance $leaveBalance)
    {
        return new LeaveBalanceResource($leaveBalance);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeaveBalance $leaveBalance)
    {
        $leaveBalance->leaveType_id = $request->input('leaveType_id');
        $leaveBalance->employee_id = $request->input('employee_id');
        $leaveBalance->balance_amount = $request->input('balance_amount');
        $leaveBalance->is_active = $request->input('is_active');
        $leaveBalance->is_trashed = $request->input('is_trashed');
        $leaveBalance->created_by = auth()->user()->id;
        $leaveBalance->save();

        return new LeaveBalanceResource($leaveBalance);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $leaveBalance = LeaveBalance::findOrFail($id);

		$is_trashed = $leaveBalance->is_trashed;

		if($is_trashed == 1) {
			$leaveBalance->delete(); // delete country
		}
		else{
            $leaveBalance->is_trashed = '1';
            $leaveBalance->deleted_at = \Carbon\Carbon::now();
            $leaveBalance->save();
        }

		return response()->json([
			"message" => "Leave Balance deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $leaveBalances = LeaveBalance::where('is_trashed',false)->get();

        return LeaveBalanceResource::collection($leaveBalances);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $leaveBalance = LeaveBalance::findOrFail($id);

        $leaveBalance->is_trashed = '0';
        $leaveBalance->deleted_at = null;
        $leaveBalance->save();

		return response()->json([
			"message" => "Leave Balance restored successfully"
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
        $leaveBalances = LeaveBalance::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($leaveBalances as $leaveBalance) {
            $is_trashed = $leaveBalance->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $leaveBalance->delete();
                $deletedPermanently[] = $leaveBalance->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $leaveBalance->is_trashed = '1';
                $leaveBalance->deleted_at = \Carbon\Carbon::now();
                $leaveBalance->save();
                $softDeleted[] = $leaveBalance->id;
            }
        }


        return response()->json([
            "message" => "Leave Balance processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }
}
