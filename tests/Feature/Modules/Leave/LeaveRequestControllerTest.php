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
use Modules\Localization\Models\CalendarYear;
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

        // Create active calendar year
        CalendarYear::create([
            'year' => Carbon::now()->year,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        // Create roles
        Role::create(['name' => 'Employee', 'guard_name' => 'api']);
        Role::create(['name' => 'Manager', 'guard_name' => 'api']);
        Role::create(['name' => 'Administrator', 'guard_name' => 'api']);

        $this->user = User::factory()->create();
        $this->user->assignRole('Employee');

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'hire_date' => Carbon::now()->subYear(),
            'dob' => '1990-01-01',
            'gender' => 'Male',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $this->leaveType = LeaveType::create([
            'type_name' => 'Casual Leave',
            'description' => 'Casual Leave',
            'leave_count' => 12,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        // Create balance and entitlement
        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'balance_amount' => 10,
        ]);

        LeaveEntitlement::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'ent_amount' => 12,
            'ent_start_date' => Carbon::now()->startOfYear(),
            'ent_end_date' => Carbon::now()->endOfYear(),
            'is_active' => 1,
            'is_trashed' => 0,
        ]);
    }

    public function test_index_as_employee()
    {
        Sanctum::actingAs($this->user, ['*'], 'api');

        LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(2),
            'reason' => 'Annual Vacation',
            'comments' => '',
            'status' => 'Pending',
        ]);

        $response = $this->getJson(route('api.leave-request.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_store_leave_request()
    {
        Sanctum::actingAs($this->user, ['*'], 'api');

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
            'status' => 'Pending',
            'is_deducted' => 1,
        ]);

        // Balance should be deducted immediately upon application (10 - 2 = 8)
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'balance_amount' => 8,
        ]);
    }

    public function test_store_leave_request_insufficient_balance_fails()
    {
        Sanctum::actingAs($this->user, ['*'], 'api');

        // Update balance to 0
        LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leavetype_id', $this->leaveType->id)
            ->update(['balance_amount' => 0]);

        $response = $this->postJson(route('api.leave-request.store'), [
            'start_date' => Carbon::now()->next('Monday')->format('Y-m-d'),
            'end_date' => Carbon::now()->next('Tuesday')->format('Y-m-d'), // 2 days
            'selectedLeaveType' => $this->leaveType->id,
            'reason' => 'Vacation',
            'leave_description' => 'Going to beach',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('Insufficient leave balance', $response->json('message'));
    }

    public function test_store_leave_request_with_quota_fallback_split()
    {
        Sanctum::actingAs($this->user, ['*'], 'api');

        // Primary Casual Leave has only 1 day
        LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leavetype_id', $this->leaveType->id)
            ->update(['balance_amount' => 1]);

        // Create Sick Leave (Tier 3) with 5 days
        $slType = LeaveType::create([
            'type_name' => 'Sick Leave',
            'description' => 'SL',
            'leave_count' => 7,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);
        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $slType->id,
            'balance_amount' => 5,
        ]);

        // Apply for 3 days of Casual Leave (1 day from CL + 2 days from SL)
        $response = $this->postJson(route('api.leave-request.store'), [
            'start_date' => Carbon::now()->next('Monday')->format('Y-m-d'),
            'end_date' => Carbon::now()->next('Wednesday')->format('Y-m-d'), // 3 days
            'selectedLeaveType' => $this->leaveType->id,
            'reason' => 'Multi-quota split test',
        ]);

        $response->assertStatus(201);

        // CL should be reduced from 1 to 0
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'balance_amount' => 0,
        ]);

        // SL should be reduced from 5 to 3
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $slType->id,
            'balance_amount' => 3,
        ]);
    }

    public function test_store_leave_request_overlap()
    {
        Sanctum::actingAs($this->user, ['*'], 'api');

        $startDate = Carbon::now()->next('Monday');
        $endDate = Carbon::now()->next('Tuesday');

        LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Existing leave',
            'comments' => '',
            'status' => 'Pending',
        ]);

        $response = $this->postJson(route('api.leave-request.store'), [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'selectedLeaveType' => $this->leaveType->id,
            'reason' => 'Overlap',
        ]);

        $response->assertStatus(422);
    }

    public function test_approve_already_deducted_leave_does_not_double_deduct()
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('Administrator');
        Employee::create([
            'user_id' => $adminUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'dob' => '1985-01-01',
            'gender' => 'Male',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        Sanctum::actingAs($adminUser, ['*'], 'api');

        // Leave already deducted at apply time: balance was 10, deducted 2 down to 8
        LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leavetype_id', $this->leaveType->id)
            ->update(['balance_amount' => 8]);

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->next('Monday'),
            'end_date' => Carbon::now()->next('Tuesday'),
            'reason' => 'Already deducted on apply',
            'comments' => '2 day(s) from Casual Leave',
            'status' => 'Pending',
            'is_deducted' => 1,
            'deductions' => [
                [
                    'leavetype_id' => $this->leaveType->id,
                    'type_name' => $this->leaveType->type_name,
                    'days' => 2,
                ],
            ],
        ]);

        $response = $this->postJson(route('api.leave-request.approve-leave'), [
            'leave_request_id' => $leave->id,
            'action' => 'Approved',
            'comment' => 'Approving already-deducted request',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Approved', $leave->fresh()->status);
        $this->assertEquals(1, $leave->fresh()->is_deducted);

        // Balance should REMAIN 8 (must NOT double-deduct to 6)
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'balance_amount' => 8,
        ]);
    }

    public function test_approve_leave_deducts_from_primary_quota()
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('Administrator');
        Employee::create([
            'user_id' => $adminUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'dob' => '1985-01-01',
            'gender' => 'Male',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        Sanctum::actingAs($adminUser, ['*'], 'api');

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->next('Monday'),
            'end_date' => Carbon::now()->next('Tuesday'),
            'reason' => 'Vacation',
            'comments' => '',
            'status' => 'Pending',
            'is_deducted' => 0,
        ]);

        $response = $this->postJson(route('api.leave-request.approve-leave'), [
            'leave_request_id' => $leave->id,
            'action' => 'Approved',
            'comment' => 'Approved by admin',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Approved', $leave->fresh()->status);
        $this->assertEquals(1, $leave->fresh()->is_deducted);

        // Deducted from 10 to 8
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'balance_amount' => 8,
        ]);
    }

    public function test_approve_leave_with_quota_fallback_split()
    {
        // Set Casual Leave balance to 0
        LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leavetype_id', $this->leaveType->id)
            ->update(['balance_amount' => 0]);

        // Create Restricted Holiday (Tier 1) with 1 day
        $rhType = LeaveType::create([
            'type_name' => 'Restricted Holiday',
            'description' => 'RH',
            'leave_count' => 2,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);
        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $rhType->id,
            'balance_amount' => 1,
        ]);

        // Create Sick Leave (Tier 3) with 2 days
        $slType = LeaveType::create([
            'type_name' => 'Sick Leave',
            'description' => 'SL',
            'leave_count' => 7,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);
        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $slType->id,
            'balance_amount' => 2,
        ]);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('Administrator');
        Employee::create([
            'user_id' => $adminUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'dob' => '1985-01-01',
            'gender' => 'Male',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        Sanctum::actingAs($adminUser, ['*'], 'api');

        // Apply for 2 days of Casual Leave
        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->next('Monday'),
            'end_date' => Carbon::now()->next('Tuesday'),
            'reason' => 'Vacation with fallback',
            'comments' => '',
            'status' => 'Pending',
            'is_deducted' => 0,
        ]);

        $response = $this->postJson(route('api.leave-request.approve-leave'), [
            'leave_request_id' => $leave->id,
            'action' => 'Approved',
            'comment' => 'Approved with split fallback',
        ]);

        $response->assertStatus(200);
        $freshLeave = $leave->fresh();
        $this->assertEquals('Approved', $freshLeave->status);
        $this->assertEquals(1, $freshLeave->is_deducted);

        // Preference order: 1 day from RH (Tier 1), 0 from CL (exhausted), 1 day from SL (Tier 3)
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $rhType->id,
            'balance_amount' => 0,
        ]);
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $slType->id,
            'balance_amount' => 1,
        ]);

        // Assert deductions JSON
        $this->assertIsArray($freshLeave->deductions);
        $this->assertCount(2, $freshLeave->deductions);
    }

    public function test_approve_leave_fails_when_all_quotas_exhausted()
    {
        // Set balance to 0
        LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leavetype_id', $this->leaveType->id)
            ->update(['balance_amount' => 0]);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('Administrator');
        Employee::create([
            'user_id' => $adminUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'dob' => '1985-01-01',
            'gender' => 'Male',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        Sanctum::actingAs($adminUser, ['*'], 'api');

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->next('Monday'),
            'end_date' => Carbon::now()->next('Tuesday'),
            'reason' => 'Exhausted quota',
            'comments' => '',
            'status' => 'Pending',
            'is_deducted' => 0,
        ]);

        $response = $this->postJson(route('api.leave-request.approve-leave'), [
            'leave_request_id' => $leave->id,
            'action' => 'Approved',
            'comment' => 'Standard approve',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => false,
        ]);
        $this->assertStringContainsString('Insufficient leave balance', $response->json('message'));
    }

    public function test_approve_leave_without_pay_when_quotas_exhausted()
    {
        // Set balance to 0
        LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leavetype_id', $this->leaveType->id)
            ->update(['balance_amount' => 0]);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('Administrator');
        Employee::create([
            'user_id' => $adminUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'dob' => '1985-01-01',
            'gender' => 'Male',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        Sanctum::actingAs($adminUser, ['*'], 'api');

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->next('Monday'),
            'end_date' => Carbon::now()->next('Tuesday'),
            'reason' => 'Approve without pay test',
            'comments' => '',
            'status' => 'Pending',
            'is_deducted' => 0,
        ]);

        $response = $this->postJson(route('api.leave-request.approve-leave'), [
            'leave_request_id' => $leave->id,
            'action' => 'ApprovedWithoutPay',
            'comment' => 'Approving without pay as quota is 0',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('ApprovedWithoutPay', $leave->fresh()->status);
        $this->assertEquals(0, $leave->fresh()->is_deducted);
    }

    public function test_reject_leave_restores_split_quotas()
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('Administrator');
        Employee::create([
            'user_id' => $adminUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'dob' => '1985-05-15',
            'gender' => 'Female',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        Sanctum::actingAs($adminUser, ['*'], 'api');

        // Create RH type
        $rhType = LeaveType::create([
            'type_name' => 'Restricted Holiday',
            'description' => 'RH',
            'leave_count' => 2,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);
        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $rhType->id,
            'balance_amount' => 0, // was deducted down to 0
        ]);

        // Primary CL was deducted down to 9
        LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leavetype_id', $this->leaveType->id)
            ->update(['balance_amount' => 9]);

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->next('Monday'),
            'end_date' => Carbon::now()->next('Tuesday'),
            'reason' => 'To be rejected',
            'comments' => '',
            'status' => 'Approved',
            'is_deducted' => 1,
            'deductions' => [
                [
                    'leavetype_id' => $this->leaveType->id,
                    'type_name' => $this->leaveType->type_name,
                    'days' => 1,
                ],
                [
                    'leavetype_id' => $rhType->id,
                    'type_name' => $rhType->type_name,
                    'days' => 1,
                ],
            ],
        ]);

        $response = $this->postJson(route('api.leave-request.reject-leave'), [
            'leave_request_id' => $leave->id,
            'comment' => 'Rejected',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Rejected', $leave->fresh()->status);
        $this->assertEquals(0, $leave->fresh()->is_deducted);

        // When rejected, both CL (9->10) and RH (0->1) are reverted back
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'balance_amount' => 10,
        ]);
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $rhType->id,
            'balance_amount' => 1,
        ]);
    }

    public function test_partial_approve_leave_recalculates_priority_and_restores_remainder()
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('Administrator');
        Employee::create([
            'user_id' => $adminUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'dob' => '1985-05-15',
            'gender' => 'Female',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        Sanctum::actingAs($adminUser, ['*'], 'api');

        // Create Sick Leave
        $slType = LeaveType::create([
            'type_name' => 'Sick Leave',
            'description' => 'SL',
            'leave_count' => 7,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);
        // Initially after application: CL was 1 and deducted to 0, SL was 5 and deducted to 3
        LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leavetype_id', $this->leaveType->id)
            ->update(['balance_amount' => 0]);

        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $slType->id,
            'balance_amount' => 3,
        ]);

        $startDate = Carbon::now()->next('Monday');
        $midDate = (clone $startDate);
        $endDate = (clone $startDate)->addDays(2);

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => $startDate,
            'end_date' => $endDate, // 3 days total
            'reason' => 'Partial approval test',
            'comments' => '',
            'status' => 'Pending',
            'is_deducted' => 1,
            'deductions' => [
                [
                    'leavetype_id' => $this->leaveType->id,
                    'type_name' => $this->leaveType->type_name,
                    'days' => 1,
                ],
                [
                    'leavetype_id' => $slType->id,
                    'type_name' => $slType->type_name,
                    'days' => 2,
                ],
            ],
        ]);

        // Approver approves day 1 (Paid), and rejects day 2 & 3
        $response = $this->postJson(route('api.leave-request.partial-approve-leave'), [
            'leave_request_id' => $leave->id,
            'partial_approvals' => [
                [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $midDate->format('Y-m-d'),
                    'status' => 'Approved',
                ],
                [
                    'start_date' => (clone $startDate)->addDay()->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'status' => 'Rejected',
                ],
            ],
            'comment' => 'Only Monday approved',
        ]);

        $response->assertStatus(200);
        $freshLeave = $leave->fresh();
        $this->assertEquals('Partially Approved', $freshLeave->status);
        $this->assertEquals(1, $freshLeave->is_deducted);

        // CL (1 day) is deducted for Monday -> CL balance is 0
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'balance_amount' => 0,
        ]);

        // SL (the 2 rejected days) are restored back -> SL balance is restored from 3 back to 5!
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leavetype_id' => $slType->id,
            'balance_amount' => 5,
        ]);

        // Deductions record only the 1 approved paid day
        $this->assertCount(1, $freshLeave->deductions);
        $this->assertEquals($this->leaveType->id, $freshLeave->deductions[0]['leavetype_id']);
        $this->assertEquals(1, $freshLeave->deductions[0]['days']);
    }

    public function test_destroy_leave_request()
    {
        Sanctum::actingAs($this->user, ['*'], 'api');

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leavetype_id' => $this->leaveType->id,
            'start_date' => Carbon::now()->addDays(10),
            'end_date' => Carbon::now()->addDays(11),
            'reason' => 'Delete me',
            'comments' => '',
            'status' => 'Pending',
        ]);

        $response = $this->deleteJson(route('api.leave-request.destroy', $leave->id));

        $response->assertStatus(202);
    }
}
