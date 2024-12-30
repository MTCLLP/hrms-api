<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Transformers\LeaveBalanceResource as LeaveBalanceResource;

class LeaveBalanceController extends Controller
{
    public function index()
    {
        $user = auth()->user(); // Get the authenticated user

        if ($user->hasRole('Administrator')) {

            $leaveBalances = LeaveBalance::with(['employee.user'])
                ->where('is_active', 1)
                ->get() // Retrieve all results (not paginated initially)
                ->groupBy('employee_id'); // Group by employee ID
        } else {
            // Employee: fetch leave balances for the specific employee
            $employee = $user->employee; // Get the employee record associated with the user

            if (!$employee) {
                abort(403, 'Employee record not found');
            }

            $leaveBalances = LeaveBalance::where('employee_id', $employee->id)
                ->where('is_active', 1)
                ->paginate(10); // Paginate for employees
        }

        // If Admin, handle grouped results manually
        $result = [];
        if ($user->hasRole('Administrator')) {
            foreach ($leaveBalances as $employeeId => $balances) {
                $firstBalance = $balances->first(); // Get first balance for employee details

                // Prepare balance types and values
                $leaveSummary = $this->formatBalances($balances);

                $result[] = array_merge([
                    'employee' => $firstBalance->employee, // Use the first record to get employee details
                ], $leaveSummary);
            }

            return response()->json($result); // Return grouped results
        }

        // For employees, format and return directly paginated results
        $result = [];
        foreach ($leaveBalances as $balance) {
            $result[] = array_merge([
                'employee' => $balance->employee,
            ], $this->formatBalances([$balance]));
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

/**
 * Format balances for output
 */
private function formatBalances($balances)
{
    $leaveSummary = [];

    foreach ($balances as $balance) {
        // Keep balance as the object for later use
        $leaveType = $balance->leavetype->type_name; // Get the leave type name
        $balanceAmount = $balance->balance_amount;   // Store balance amount in a separate variable
        $id = $balance->id;                          // Now this works because $balance is still an object

        // Ensure the leave type key is unique and balances are properly stored
        $leaveSummary[$leaveType] = [
            'balance' => $balanceAmount,
            'id' => $id
        ];
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
