<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveType;
use Modules\Employee\Models\Employee;
use Modules\Localization\Models\CalendarYear;
use Modules\Leave\Transformers\LeaveBalanceResource as LeaveBalanceResource;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LeaveBalanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user(); // Get the authenticated user

        // Fetch all leave types for consistency
        $leaveTypes = LeaveType::pluck('type_name', 'id'); // Assuming LeaveType model exists

        // Result container
        $result = [];

        if ($user->hasRole(['Administrator', 'Superadmin'])) {
            // Admin: fetch all leave balances for active employees with active users
            $query = LeaveBalance::with(['employee.user', 'leavetype'])
                ->where('is_active', 1)
                ->whereHas('employee', function ($eq) {
                    $eq->where('is_trashed', false)
                       ->where('is_active', true);
                    if (Schema::hasColumn('users', 'is_active')) {
                        $eq->whereHas('user', function ($uq) {
                            $uq->where('is_active', true);
                        });
                    } else {
                        $eq->whereHas('user');
                    }
                });

            $leaveBalances = $query->get()->groupBy('employee_id');

            foreach ($leaveBalances as $employeeId => $balances) {
                $firstBalance = $balances->first();
                $employee = $firstBalance ? $firstBalance->employee : null;

                if (!$employee || !$employee->user) {
                    continue;
                }

                // Prepare entitlement summary for each leave type
                $leaveSummary = $this->formatBalances($balances, $leaveTypes);

                $result[] = [
                    'id' => $employee->id,
                    'employee_id' => $employee->id,
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->user->name,
                    ],
                    'leave_balances' => $leaveSummary,
                ];
            }
        } else {
            // Employee: fetch entitlements for the logged-in employee
            $employee = $user->employee;

            if (! $employee) {
                abort(403, 'Employee record not found');
            }

            $balances = LeaveBalance::with(['leavetype'])
                ->where('employee_id', $employee->id)
                ->where('is_active', 1)
                ->get();

            // Prepare entitlement summary for each leave type
            $leaveSummary = $this->formatBalances($balances, $leaveTypes);

            $result[] = [
                'id' => $employee->id,
                'employee_id' => $employee->id,
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
    private function formatBalances($balances, $leaveTypes = null)
    {
        if (!$leaveTypes) {
            $leaveTypes = LeaveType::pluck('type_name', 'id');
        }

        $leaveSummary = [];

        // Initialize all leave types with 0
        foreach ($leaveTypes as $typeName) {
            $leaveSummary[$typeName] = 0; // Default value
        }

        // Populate actual values from entitlements
        foreach ($balances as $balance) {
            $leaveType = $balance->leaveType ? $balance->leaveType->type_name : null;
            if ($leaveType) {
                $leaveSummary[$leaveType] = (float) $balance->balance_amount;
            }
        }

        return $leaveSummary;
    }

    /**
     * Display a paginated listing of the resource.
     *
     * @return Response
     */
    public function paginated(Request $request)
    {
        $user = auth()->user(); // Get the authenticated user
        $perPage = (int) $request->get('per_page', 10);
        $leaveTypes = LeaveType::pluck('type_name', 'id');

        if ($user->hasRole(['Administrator', 'Superadmin'])) {
            // Admin: paginate active employees
            $employeeQuery = Employee::with('user')
                ->where('employees.is_trashed', false)
                ->where('employees.is_active', true);

            if (Schema::hasColumn('users', 'is_active')) {
                $employeeQuery->whereHas('user', function ($uq) {
                    $uq->where('is_active', true);
                });
            } else {
                $employeeQuery->whereHas('user');
            }

            if ($request->filled('name')) {
                $employeeQuery->whereHas('user', function ($uq) use ($request) {
                    $uq->where('name', 'like', '%' . $request->name . '%');
                });
            }

            $paginatedEmployees = $employeeQuery
                ->join('users', 'employees.user_id', '=', 'users.id')
                ->orderBy('users.name', 'asc')
                ->select('employees.*')
                ->paginate($perPage);

            $employeeIds = $paginatedEmployees->pluck('id');

            // Fetch balances for these employees in the current financial period
            $activeYear = CalendarYear::where('is_active', 1)->first();
            $calendarYearId = $request->input('calendar_year_id', $activeYear ? $activeYear->id : null);

            $balanceQuery = LeaveBalance::withoutGlobalScopes()
                ->whereIn('employee_id', $employeeIds)
                ->where('is_active', 1);

            if ($calendarYearId) {
                $balanceQuery->where('calendar_year_id', $calendarYearId);
            }

            if (Schema::hasColumn('leave_balances', 'is_trashed')) {
                $balanceQuery->where('is_trashed', 0);
            }

            $leaveBalances = $balanceQuery->with('leaveType')->get()->groupBy('employee_id');

            $result = [];
            foreach ($paginatedEmployees as $employee) {
                $balances = $leaveBalances->get($employee->id, collect());
                $leaveSummary = $this->formatBalances($balances, $leaveTypes);

                $result[] = [
                    'id' => $employee->id,
                    'employee_id' => $employee->id,
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->user ? $employee->user->name : '',
                    ],
                    'leave_balances' => $leaveSummary,
                ];
            }

            return response()->json([
                'data' => $result,
                'meta' => [
                    'current_page' => $paginatedEmployees->currentPage(),
                    'last_page' => $paginatedEmployees->lastPage(),
                    'per_page' => $paginatedEmployees->perPage(),
                    'total' => $paginatedEmployees->total(),
                ],
                'links' => [
                    'first' => $paginatedEmployees->url(1),
                    'last' => $paginatedEmployees->url($paginatedEmployees->lastPage()),
                    'prev' => $paginatedEmployees->previousPageUrl(),
                    'next' => $paginatedEmployees->nextPageUrl(),
                ],
            ]);
        } else {
            // Employee: fetch leave balances for the logged-in employee
            $employee = $user->employee;

            if (! $employee) {
                abort(403, 'Employee record not found');
            }

            $activeYear = CalendarYear::where('is_active', 1)->first();
            $calendarYearId = $request->input('calendar_year_id', $activeYear ? $activeYear->id : null);

            $balanceQuery = LeaveBalance::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('is_active', 1);

            if ($calendarYearId) {
                $balanceQuery->where('calendar_year_id', $calendarYearId);
            }

            if (Schema::hasColumn('leave_balances', 'is_trashed')) {
                $balanceQuery->where('is_trashed', 0);
            }

            $balances = $balanceQuery->with('leaveType')->get();
            $leaveSummary = $this->formatBalances($balances, $leaveTypes);

            $result = [
                [
                    'id' => $employee->id,
                    'employee_id' => $employee->id,
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $user->name,
                    ],
                    'leave_balances' => $leaveSummary,
                ]
            ];

            return response()->json([
                'data' => $result,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 1,
                ],
                'links' => [
                    'first' => null,
                    'last' => null,
                    'prev' => null,
                    'next' => null,
                ],
            ]);
        }
    }

    public function getLeaveBalance(Request $request)
    {
        $user = auth()->user();
        if (($user->hasRole('Administrator') || $user->hasRole('Superadmin')) && $request->filled('employee_id')) {
            $employeeId = $request->employee_id;
        } else {
            $employeeId = $user->employee ? $user->employee->id : null;
        }

        if (!$employeeId) {
            return response()->json([]);
        }

        $query = LeaveBalance::with(['leavetype'])
            ->where('employee_id', $employeeId)
            ->where('is_active', 1);

        if (Schema::hasColumn('leave_balances', 'is_trashed')) {
            $query->where('is_trashed', 0);
        }

        $balances = $query->get();

        return response()->json($balances);
    }

    /**
     * Get all leave types and the employee's current balances for a specific financial period.
     */
    public function getEmployeeBalances($employeeId)
    {
        $user = auth()->user();
        if (!$user->hasRole(['Administrator', 'Superadmin'])) {
            if (!$user->employee || $user->employee->id != $employeeId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $employee = Employee::with('user')->findOrFail($employeeId);
        $leaveTypes = LeaveType::all();

        $activeYear = CalendarYear::where('is_active', 1)->first();
        $calendarYearId = request('calendar_year_id', $activeYear ? $activeYear->id : null);

        $query = LeaveBalance::withoutGlobalScopes()
            ->where('employee_id', $employeeId);

        if ($calendarYearId) {
            $query->where('calendar_year_id', $calendarYearId);
        }

        if (Schema::hasColumn('leave_balances', 'is_trashed')) {
            $query->where('is_trashed', 0);
        }

        $balances = $query->get()->keyBy('leavetype_id');

        $result = [];
        foreach ($leaveTypes as $type) {
            $existing = $balances->get($type->id);
            $result[] = [
                'leavetype_id' => $type->id,
                'leaveType_id' => $type->id,
                'type_name' => $type->type_name,
                'balance_amount' => $existing ? (float) $existing->balance_amount : 0,
                'balance_id' => $existing ? $existing->id : null,
            ];
        }

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user ? $employee->user->name : '',
            ],
            'financial_year' => $activeYear ? $activeYear->year : date('Y'),
            'calendar_year_id' => $calendarYearId,
            'has_existing_balances' => $balances->isNotEmpty(),
            'balances' => $result,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Renderable
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasRole(['Administrator', 'Superadmin'])) {
            return response()->json(['message' => 'Unauthorized. Only Superadmin/Administrator can set leave balance.'], 403);
        }

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $employeeId = $request->input('employee_id');
        $hasTrashed = Schema::hasColumn('leave_balances', 'is_trashed');

        $activeYear = CalendarYear::where('is_active', 1)->first();
        $calendarYearId = $request->input('calendar_year_id', $activeYear ? $activeYear->id : null);

        // Bulk balances array
        if ($request->has('balances') && is_array($request->input('balances'))) {
            $saved = [];
            foreach ($request->input('balances') as $item) {
                $leaveTypeId = $item['leavetype_id'] ?? $item['leaveType_id'] ?? null;
                $balanceAmount = $item['balance_amount'] ?? 0;

                if ($leaveTypeId) {
                    $query = LeaveBalance::withoutGlobalScopes()
                        ->where('employee_id', $employeeId)
                        ->where('leavetype_id', $leaveTypeId);

                    if ($calendarYearId) {
                        $query->where('calendar_year_id', $calendarYearId);
                    }
                    if ($hasTrashed) {
                        $query->where('is_trashed', 0);
                    }
                    $leaveBalance = $query->first();

                    if ($leaveBalance) {
                        $leaveBalance->balance_amount = $balanceAmount;
                        $leaveBalance->is_active = 1;
                        $leaveBalance->created_by = $user->id;
                        $leaveBalance->save();
                    } else {
                        $createData = [
                            'employee_id' => $employeeId,
                            'leavetype_id' => $leaveTypeId,
                            'calendar_year_id' => $calendarYearId,
                            'balance_amount' => $balanceAmount,
                            'created_by' => $user->id,
                            'is_active' => 1,
                        ];
                        if ($hasTrashed) {
                            $createData['is_trashed'] = 0;
                        }
                        $leaveBalance = LeaveBalance::create($createData);
                    }

                    $saved[] = $leaveBalance;
                }
            }

            return response()->json([
                'message' => 'Leave balances saved successfully',
                'data' => LeaveBalanceResource::collection(collect($saved)),
            ], 201);
        }

        // Single balance
        $request->validate([
            'balance_amount' => 'required|numeric|min:0',
        ]);

        $leaveTypeId = $request->input('leavetype_id', $request->input('leaveType_id'));

        $query = LeaveBalance::withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('leavetype_id', $leaveTypeId);

        if ($calendarYearId) {
            $query->where('calendar_year_id', $calendarYearId);
        }
        if ($hasTrashed) {
            $query->where('is_trashed', 0);
        }
        $leaveBalance = $query->first();

        if ($leaveBalance) {
            $leaveBalance->balance_amount = $request->input('balance_amount');
            $leaveBalance->is_active = $request->input('is_active', 1);
            $leaveBalance->created_by = $user->id;
            $leaveBalance->save();
        } else {
            $createData = [
                'employee_id' => $employeeId,
                'leavetype_id' => $leaveTypeId,
                'calendar_year_id' => $calendarYearId,
                'balance_amount' => $request->input('balance_amount'),
                'created_by' => $user->id,
                'is_active' => $request->input('is_active', 1),
            ];
            if ($hasTrashed) {
                $createData['is_trashed'] = 0;
            }
            $leaveBalance = LeaveBalance::create($createData);
        }

        return new LeaveBalanceResource($leaveBalance);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        $balance = LeaveBalance::with(['employee.user', 'leaveType'])->find($id);
        if ($balance) {
            return new LeaveBalanceResource($balance);
        }

        // If not found as balance id, check if it's an employee id
        $employee = Employee::find($id);
        if ($employee) {
            return $this->getEmployeeBalances($id);
        }

        abort(404, 'Leave balance not found');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user->hasRole(['Administrator', 'Superadmin'])) {
            return response()->json(['message' => 'Unauthorized. Only Superadmin/Administrator can update leave balance.'], 403);
        }

        $hasTrashed = Schema::hasColumn('leave_balances', 'is_trashed');
        $activeYear = CalendarYear::where('is_active', 1)->first();
        $calendarYearId = $request->input('calendar_year_id', $activeYear ? $activeYear->id : null);

        // Bulk balances array (by employee)
        if ($request->has('balances') && is_array($request->input('balances'))) {
            $employeeId = $request->input('employee_id', $id);
            $saved = [];

            foreach ($request->input('balances') as $item) {
                $leaveTypeId = $item['leavetype_id'] ?? $item['leaveType_id'] ?? null;
                $balanceAmount = $item['balance_amount'] ?? 0;

                if ($leaveTypeId) {
                    $query = LeaveBalance::withoutGlobalScopes()
                        ->where('employee_id', $employeeId)
                        ->where('leavetype_id', $leaveTypeId);

                    if ($calendarYearId) {
                        $query->where('calendar_year_id', $calendarYearId);
                    }
                    if ($hasTrashed) {
                        $query->where('is_trashed', 0);
                    }
                    $leaveBalance = $query->first();

                    if ($leaveBalance) {
                        $leaveBalance->balance_amount = $balanceAmount;
                        $leaveBalance->is_active = 1;
                        $leaveBalance->created_by = $user->id;
                        $leaveBalance->save();
                    } else {
                        $createData = [
                            'employee_id' => $employeeId,
                            'leavetype_id' => $leaveTypeId,
                            'calendar_year_id' => $calendarYearId,
                            'balance_amount' => $balanceAmount,
                            'created_by' => $user->id,
                            'is_active' => 1,
                        ];
                        if ($hasTrashed) {
                            $createData['is_trashed'] = 0;
                        }
                        $leaveBalance = LeaveBalance::create($createData);
                    }

                    $saved[] = $leaveBalance;
                }
            }

            return response()->json([
                'message' => 'Leave balances updated successfully',
                'data' => LeaveBalanceResource::collection(collect($saved)),
            ]);
        }

        // Single balance update
        $leaveBalance = LeaveBalance::find($id);
        if (!$leaveBalance) {
            $leaveTypeId = $request->input('leavetype_id', $request->input('leaveType_id'));
            $query = LeaveBalance::withoutGlobalScopes()
                ->where('employee_id', $id)
                ->where('leavetype_id', $leaveTypeId);

            if ($calendarYearId) {
                $query->where('calendar_year_id', $calendarYearId);
            }
            if ($hasTrashed) {
                $query->where('is_trashed', 0);
            }
            $leaveBalance = $query->first();

            if (!$leaveBalance) {
                $leaveBalance = new LeaveBalance();
                $leaveBalance->employee_id = $id;
                $leaveBalance->leavetype_id = $leaveTypeId;
                $leaveBalance->calendar_year_id = $calendarYearId;
                if ($hasTrashed) {
                    $leaveBalance->is_trashed = 0;
                }
            }
        }

        if ($request->filled('leaveType_id') || $request->filled('leavetype_id')) {
            $leaveBalance->leavetype_id = $request->input('leavetype_id', $request->input('leaveType_id'));
        }
        if ($request->filled('employee_id')) {
            $leaveBalance->employee_id = $request->input('employee_id');
        }
        if ($request->has('balance_amount') && $request->input('balance_amount') !== null && $request->input('balance_amount') !== '') {
            $leaveBalance->balance_amount = $request->input('balance_amount');
        }
        if ($request->has('is_active')) {
            $leaveBalance->is_active = $request->input('is_active');
        }
        if ($request->has('is_trashed') && $hasTrashed) {
            $leaveBalance->is_trashed = $request->input('is_trashed');
        }

        $leaveBalance->created_by = $user->id;
        $leaveBalance->save();

        return new LeaveBalanceResource($leaveBalance);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $leaveBalance = LeaveBalance::findOrFail($id);

        if (Schema::hasColumn('leave_balances', 'is_trashed')) {
            if ($leaveBalance->is_trashed == 1) {
                $leaveBalance->delete();
            } else {
                $leaveBalance->is_trashed = 1;
                $leaveBalance->deleted_at = \Carbon\Carbon::now();
                $leaveBalance->save();
            }
        } else {
            $leaveBalance->delete();
        }

        return response()->json([
            "message" => "Leave Balance deleted"
        ], 202);
    }

    /**
     * Display a listing of the trashed items.
     *
     * @return Response
     */
    public function trash()
    {
        if (Schema::hasColumn('leave_balances', 'is_trashed')) {
            $leaveBalances = LeaveBalance::where('is_trashed', true)->get();
        } else {
            $leaveBalances = collect([]);
        }

        return LeaveBalanceResource::collection($leaveBalances);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $leaveBalance = LeaveBalance::findOrFail($id);

        if (Schema::hasColumn('leave_balances', 'is_trashed')) {
            $leaveBalance->is_trashed = 0;
            $leaveBalance->deleted_at = null;
            $leaveBalance->save();
        }

        return response()->json([
            "message" => "Leave Balance restored successfully"
        ], 202);
    }

    /**
     * Remove multiple specified resources from storage.
     */
    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids;

        if (empty($ids)) {
            return response()->json([
                'message' => 'No IDs provided',
            ], 400);
        }

        $leaveBalances = LeaveBalance::whereIn('id', $ids)->get();
        $hasTrashed = Schema::hasColumn('leave_balances', 'is_trashed');

        $deletedPermanently = [];
        $softDeleted = [];

        foreach ($leaveBalances as $leaveBalance) {
            if ($hasTrashed && $leaveBalance->is_trashed != 1) {
                $leaveBalance->is_trashed = 1;
                $leaveBalance->deleted_at = \Carbon\Carbon::now();
                $leaveBalance->save();
                $softDeleted[] = $leaveBalance->id;
            } else {
                $leaveBalance->delete();
                $deletedPermanently[] = $leaveBalance->id;
            }
        }

        return response()->json([
            'message' => 'Leave Balance processed',
            'deleted_permanently' => $deletedPermanently,
            'soft_deleted' => $softDeleted,
        ], 202);
    }
}
