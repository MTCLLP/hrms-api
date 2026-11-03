<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Models\LeaveEntitlement;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Transformers\LeaveEntitlementResource;
use Modules\Employee\Models\Employee;
use Illuminate\Support\Facades\Schema;

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

        if ($user->hasRole(['Administrator', 'Superadmin'])) {
            // Admin: fetch all leave entitlements ONLY for active, non-trashed employees with active users
            $leaveEntitlements = LeaveEntitlement::with(['employee.user', 'leavetype'])
                ->where('is_active', 1)
                ->whereHas('employee', function ($query) {
                    $query->where('is_trashed', false)
                          ->where('is_active', true);
                    if (Schema::hasColumn('users', 'is_active')) {
                        $query->whereHas('user', function ($uq) {
                            $uq->where('is_active', true);
                        });
                    } else {
                        $query->whereHas('user');
                    }
                })
                ->get()
                ->groupBy('employee_id');

            foreach ($leaveEntitlements as $employeeId => $entitlements) {
                $firstEntitlement = $entitlements->first();
                $employee = $firstEntitlement->employee;

                // Extra safety check: ensures $employee exists before accessing properties
                if ($employee && $employee->user) {
                    $leaveSummary = $this->formatEntitlements($entitlements, $leaveTypes);

                    $result[] = [
                        'employee' => [
                            'id' => $employee->id,
                            'name' => $employee->user->name,
                        ],
                        'leave_entitlements' => $leaveSummary,
                    ];
                }
            }
        } else {
            // Employee: fetch entitlements for the logged-in employee
            $employee = $user->employee;

            if (! $employee) {
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
            $leaveType = $entitlement->leavetype ? $entitlement->leavetype->type_name : null;
            if ($leaveType) {
                $balance = (float) $entitlement->ent_amount;
                $leaveSummary[$leaveType] = $balance;
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
            // Admin: paginate active, non-trashed employees with active users
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

            // Fetch entitlements for these employees
            $entitlementQuery = LeaveEntitlement::with('leavetype')
                ->whereIn('employee_id', $employeeIds)
                ->where('is_active', 1);

            if (Schema::hasColumn('leave_entitlements', 'is_trashed')) {
                $entitlementQuery->where('is_trashed', 0);
            }

            if ($request->filled('startDate')) {
                $entitlementQuery->whereDate('ent_start_date', '>=', $request->startDate);
            }
            if ($request->filled('endDate')) {
                $entitlementQuery->whereDate('ent_end_date', '<=', $request->endDate);
            }

            $leaveEntitlements = $entitlementQuery->get()->groupBy('employee_id');

            $result = [];
            foreach ($paginatedEmployees as $employee) {
                $entitlements = $leaveEntitlements->get($employee->id, collect());
                $leaveSummary = $this->formatEntitlements($entitlements, $leaveTypes);

                $result[] = [
                    'id' => $employee->id,
                    'employee_id' => $employee->id,
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->user ? $employee->user->name : '',
                    ],
                    'leave_entitlements' => $leaveSummary,
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
            // Employee: fetch entitlements for the logged-in employee
            $employee = $user->employee;

            if (!$employee) {
                abort(403, 'Employee record not found');
            }

            $entitlementQuery = LeaveEntitlement::with('leavetype')
                ->where('employee_id', $employee->id)
                ->where('is_active', 1);

            if (Schema::hasColumn('leave_entitlements', 'is_trashed')) {
                $entitlementQuery->where('is_trashed', 0);
            }

            $entitlements = $entitlementQuery->get();
            $leaveSummary = $this->formatEntitlements($entitlements, $leaveTypes);

            $result = [
                [
                    'id' => $employee->id,
                    'employee_id' => $employee->id,
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $user->name,
                    ],
                    'leave_entitlements' => $leaveSummary,
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

    /**
     * Store a newly created resource in storage.
     *
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
     * Get all leave types and the employee's current entitlements.
     */
    public function getEmployeeEntitlements($employeeId)
    {
        $user = auth()->user();
        if (!$user->hasRole(['Administrator', 'Superadmin'])) {
            if (!$user->employee || $user->employee->id != $employeeId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $employee = Employee::with('user')->findOrFail($employeeId);
        $leaveTypes = LeaveType::all();

        $query = LeaveEntitlement::where('employee_id', $employeeId)
            ->where('is_active', 1);

        if (Schema::hasColumn('leave_entitlements', 'is_trashed')) {
            $query->where('is_trashed', 0);
        }

        $entitlements = $query->get()->keyBy('leavetype_id');

        $firstEnt = $entitlements->first();
        $entStartDate = $firstEnt ? $firstEnt->ent_start_date : (date('Y') . '-01-01');
        $entEndDate = $firstEnt ? $firstEnt->ent_end_date : (date('Y') . '-12-31');

        $result = [];
        foreach ($leaveTypes as $type) {
            $existing = $entitlements->get($type->id);
            $result[] = [
                'leavetype_id' => $type->id,
                'type_name' => $type->type_name,
                'ent_amount' => $existing ? (float) $existing->ent_amount : 0,
                'id' => $existing ? $existing->id : null,
            ];
        }

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user ? $employee->user->name : '',
            ],
            'ent_start_date' => $entStartDate,
            'ent_end_date' => $entEndDate,
            'entitlements' => $result,
        ]);
    }

    /**
     * Upsert all leave entitlements for an employee.
     */
    public function updateEmployeeEntitlements(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasRole(['Administrator', 'Superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'ent_start_date' => 'required|date',
            'ent_end_date' => 'required|date|after_or_equal:ent_start_date',
            'entitlements' => 'required|array',
            'entitlements.*.leavetype_id' => 'required|exists:leave_types,id',
            'entitlements.*.ent_amount' => 'required|numeric|min:0',
        ]);

        $employeeId = $request->input('employee_id');
        $startDate = $request->input('ent_start_date');
        $endDate = $request->input('ent_end_date');
        $entitlements = $request->input('entitlements');

        foreach ($entitlements as $item) {
            $leaveTypeId = $item['leavetype_id'];
            $amount = (float) $item['ent_amount'];

            // Find existing active entitlement for employee and leavetype
            $existing = LeaveEntitlement::where('employee_id', $employeeId)
                ->where('leavetype_id', $leaveTypeId)
                ->first();

            if ($existing) {
                $existing->ent_amount = $amount;
                $existing->ent_start_date = $startDate;
                $existing->ent_end_date = $endDate;
                $existing->is_active = 1;
                if (Schema::hasColumn('leave_entitlements', 'is_trashed')) {
                    $existing->is_trashed = 0;
                }
                $existing->save();
            } else {
                LeaveEntitlement::create([
                    'employee_id' => $employeeId,
                    'leavetype_id' => $leaveTypeId,
                    'ent_amount' => $amount,
                    'ent_start_date' => $startDate,
                    'ent_end_date' => $endDate,
                    'created_by' => $user->id,
                    'is_active' => 1,
                    'is_trashed' => 0,
                ]);
            }
        }

        return response()->json([
            'message' => 'Leave entitlements updated successfully',
        ]);
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

        if ($is_trashed == 1) {
            $leaveEntitlement->delete(); // delete country
        } else {
            $leaveEntitlement->is_trashed = '1';
            $leaveEntitlement->deleted_at = \Carbon\Carbon::now();
            $leaveEntitlement->save();
        }

        return response()->json([
            'message' => 'Leave Entitlement deleted',
        ], 202);
    }

    /**
     * Display a listing of the trashed items.
     *
     * @return Response
     */
    public function trash()
    {
        $leaveEntitlements = LeaveEntitlement::where('is_trashed', false)->get();

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
            'message' => 'Leave Entitlement restored successfully',
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

     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids; // Get the array of IDs from the request

        if (empty($ids)) {
            return response()->json([
                'message' => 'No department IDs provided',
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
            'message' => 'Leave Entitlement processed',
            'deleted_permanently' => $deletedPermanently,
            'soft_deleted' => $softDeleted,
        ], 202);
    }
}
