<?php

namespace Tests\Feature\Modules\Leave;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveEntitlement;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

class LeaveRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $employee;
    protected $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'Employee', 'guard_name' => 'api']);
        Role::create(['name' => 'Manager', 'guard_name' => 'api']);
        Role::create(['name' => 'Administrator', 'guard_name' => 'api']);

        $this->user = User::factory()->create();
        $this->user->assignRole('Employee');

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'hire_date' => Carbon::now()->subYear(),
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $this->leaveType = LeaveType::create([
            'type_name' => 'Casual Leave',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        // Create balance and entitlement
        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'balance_amount' => 10,
            'year' => Carbon::now()->year,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        LeaveEntitlement::create([
            'employee_id' => $this->employee->id,
            'leaveType_id' => $this->leaveType->id,
            'ent_amount' => 12,
            'year' => Carbon::now()->year,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);
    }

    public function test_index_as_employee()
    {
        Sanctum::actingAs($this->user);

        LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(2),
            'status' => 'Pending',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->getJson(route('api.leave-request.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_store_leave_request()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('api.leave-request.store'), [
            'start_date' => Carbon::now()->next('Monday')->format('Y-m-d'),
            'end_date' => Carbon::now()->next('Tuesday')->format('Y-m-d'),
            'selectedLeaveType' => $this->leaveType->id,
            'reason' => 'Vacation',
            'leave_description' => 'Going to beach',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->employee->id,
            'reason' => 'Vacation',
        ]);
    }

    public function test_store_leave_request_overlap()
    {
        Sanctum::actingAs($this->user);

        $startDate = Carbon::now()->next('Monday');
        $endDate = Carbon::now()->next('Tuesday');

        LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'Pending',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->postJson(route('api.leave-request.store'), [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'selectedLeaveType' => $this->leaveType->id,
            'reason' => 'Overlap',
        ]);

        $response->assertStatus(422);
    }

    public function test_approve_leave()
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('Administrator');
        $adminEmployee = Employee::create([
            'user_id' => $adminUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        Sanctum::actingAs($adminUser);

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->next('Monday'),
            'end_date' => Carbon::now()->next('Tuesday'),
            'status' => 'Pending',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->postJson(route('api.leave-request.approve-leave'), [
            'leave_request_id' => $leave->id,
            'action' => 'Approved',
            'comment' => 'Approved by admin',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Approved', $leave->fresh()->status);

        // Check balance deduction (2 days)
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'balance_amount' => 8, // 10 - 2
        ]);
    }

    public function test_reject_leave()
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('Administrator');
        $adminEmployee = Employee::create([
            'user_id' => $adminUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        Sanctum::actingAs($adminUser);

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->next('Monday'),
            'end_date' => Carbon::now()->next('Tuesday'),
            'status' => 'Pending',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->postJson(route('api.leave-request.reject-leave'), [
            'leave_request_id' => $leave->id,
            'comment' => 'Rejected',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Rejected', $leave->fresh()->status);
    }

    public function test_destroy_leave_request()
    {
        Sanctum::actingAs($this->user);

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->addDays(10),
            'end_date' => Carbon::now()->addDays(11),
            'status' => 'Pending',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->deleteJson(route('api.leave-request.destroy', $leave->id));

        $response->assertStatus(202);
        $this->assertEquals(1, $leave->fresh()->is_trashed);
    }
}
