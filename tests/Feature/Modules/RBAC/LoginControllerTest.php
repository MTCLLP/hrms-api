<?php

namespace Tests\Feature\Modules\RBAC;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Employee', 'guard_name' => 'api']);
    }

    public function test_login_success()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password', // Mutator hashes it
            'email_verified_at' => Carbon::now(),
            'mobile_verified_at' => Carbon::now(),
        ]);
        $user->assignRole('Employee');

        Employee::create([
            'user_id' => $user->id,
            'hire_date' => Carbon::now(),
            'is_active' => 1,
            'is_trashed' => 0,
        ]);

        $response = $this->postJson(route('login.api'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);
    }

    public function test_login_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson(route('login.api'), [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_unverified()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson(route('login.api'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }
}
