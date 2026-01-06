<?php

namespace Tests\Feature\Modules\RBAC;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\Holiday;
use Modules\Leave\Models\LeaveApproval;
use Modules\Employee\Models\JobReporting;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'hire_date' => Carbon::now()->subYear(),
            'is_active' => 1,
            'is_trashed' => 0,
        ]);
    }

    public function test_get_employees()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(route('api.dashboard.getEmployees'));

        $response->assertStatus(200);
        $response->assertJson(1);
    }

    public function test_get_pending_leaves()
    {
        Sanctum::actingAs($this->user);

        LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(2),
            'status' => 'pending',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->getJson(route('api.dashboard.getPendingLeaves'));

        $response->assertStatus(200);
        $response->assertJson(1);
    }

    public function test_get_subordinate_leaves()
    {
        Sanctum::actingAs($this->user);

        $subordinateUser = User::factory()->create();
        $subordinateEmployee = Employee::create([
            'user_id' => $subordinateUser->id,
            'hire_date' => Carbon::now()->subYear(),
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        JobReporting::create([
            'superior_id' => $this->employee->id,
            'subordinate_id' => $subordinateEmployee->id,
            'reporting_method_id' => 1,
        ]);

        LeaveRequest::create([
            'employee_id' => $subordinateEmployee->id,
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(2),
            'status' => 'pending',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->getJson(route('api.dashboard.getSubordinateLeaves'));

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_get_last_leave()
    {
        Sanctum::actingAs($this->user);

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->subDays(4),
            'status' => 'Approved',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->getJson(route('api.dashboard.getLastLeave'));

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $leave->id]);
    }

    public function test_display_calendar()
    {
        Sanctum::actingAs($this->user);

        Holiday::create([
            'name' => 'Test Holiday',
            'date' => Carbon::now()->addDays(10),
            'is_active' => 1,
        ]);

        LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(1),
            'status' => 'Approved',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->getJson(route('api.dashboard.displayCalendar'));

        $response->assertStatus(200);
        $response->assertJsonFragment(['title' => 'Test Holiday']);
        $response->assertJsonFragment(['type' => 'leave']);
    }

    public function test_upcoming_leaves()
    {
        Sanctum::actingAs($this->user);

        LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'start_date' => Carbon::now()->addDays(5),
            'end_date' => Carbon::now()->addDays(6),
            'status' => 'Approved',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->getJson(route('api.dashboard.upcomingLeaves'));

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_upcoming_birthdays()
    {
        Sanctum::actingAs($this->user);

        $this->employee->update(['dob' => Carbon::now()->addDays(2)]);

        $response = $this->getJson(route('api.dashboard.upcomingBirthdays'));

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $this->employee->id]);
    }

    public function test_get_upcoming_holiday()
    {
        Sanctum::actingAs($this->user);

        Holiday::create([
            'name' => 'Upcoming Holiday',
            'date' => Carbon::now()->addDays(5),
            'is_active' => 1,
        ]);

        $response = $this->getJson(route('api.dashboard.getUpcomingHoliday'));

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Upcoming Holiday']);
    }

    public function test_get_employee_monthly_leaves()
    {
        Sanctum::actingAs($this->user);

        $month = Carbon::now()->format('Y-m');

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'start_date' => Carbon::now()->startOfMonth()->addDays(1),
            'end_date' => Carbon::now()->startOfMonth()->addDays(2),
            'status' => 'Approved',
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        LeaveApproval::create([
            'leaverequest_id' => $leave->id,
            'status' => 'Approved',
            'approver_id' => $this->employee->id,
            'start_date' => $leave->start_date,
            'end_date' => $leave->end_date,
            'total_days' => 2,
            'isPaidLeave' => 1,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->getJson(route('api.dashboard.getEmployeeMonthlyLeaves', ['month' => $month]));

        $response->assertStatus(200);
        $response->assertJsonFragment(['employee_id' => $this->employee->id]);
    }

    public function test_upcoming_confirmations()
    {
        Sanctum::actingAs($this->user);

        $employee = Employee::create([
            'user_id' => User::factory()->create()->id,
            'hire_date' => Carbon::now()->subMonths(6)->addDays(10),
            'confirmation_date' => null,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->getJson(route('api.dashboard.upcomingConfirmations'));

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $employee->id]);
    }

    public function test_confirm_employee()
    {
        Sanctum::actingAs($this->user);

        $employee = Employee::create([
            'user_id' => User::factory()->create()->id,
            'hire_date' => Carbon::now()->subMonths(6),
            'confirmation_date' => null,
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->postJson(route('api.dashboard.confirmEmployee'), [
            'employeeId' => $employee->id,
            'confirmation_date' => Carbon::now()->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($employee->fresh()->confirmation_date);
    }

    public function test_extend_confirmation()
    {
        Sanctum::actingAs($this->user);

        $employee = Employee::create([
            'user_id' => User::factory()->create()->id,
            'hire_date' => Carbon::now()->subMonths(6),
            'confirmation_date' => Carbon::now()->subDays(1),
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->postJson(route('api.dashboard.extendConfirmation'), [
            'employeeId' => $employee->id,
            'newDate' => Carbon::now()->addMonths(1)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $this->assertEquals(Carbon::now()->addMonths(1)->format('Y-m-d'), $employee->fresh()->confirmation_date);
    }
}
