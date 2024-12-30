<?php

namespace Modules\RBAC\Http\Controllers\Api\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules\Password as RulesPassword;
use App\Notifications\EmailVerification;
use Modules\RBAC\Models\User;
use Hash;
use Carbon\Carbon;
use Modules\RBAC\Events\UserRegistered;

class RegisterController extends Controller
{
    /**
     * Create User
     * @param Request $request
     * @return User
     */
    public function createUser(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'nullable|email|unique:users,email',
                'mobile' => 'required|numeric|unique:users,mobile',
                'password' => 'required|max:16'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422); // Use 422 for validation errors
            }

            // Create user
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'mobile' => $request->input('mobile'),
                'password' => $request->input('password') //Mutator function inside User model will handle hash,
            ]);

            $user->assignRole('User');

            // // Trigger user registration event
            event(new UserRegistered($user));

            return response()->json([
                'status' => true,
                'message' => 'User registered successfully. OTP sent.',
                'token' => $user->createToken("API TOKEN")->plainTextToken
            ], 201); // Use 201 for resource creation success
        } catch (\Exception $e) {
            // Handle exception gracefully
            return response()->json([
                'status' => false,
                'message' => 'An error occurred during user creation.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
