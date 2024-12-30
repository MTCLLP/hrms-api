<?php

namespace Modules\RBAC\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Modules\RBAC\Models\User;
use Hash;

class LoginController extends Controller
{
    public function login(Request $request)
{
    try {
        // Validate input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422); // Use 422 for validation errors
        }

        // Attempt authentication
        if (!Auth::attempt($request->only(['email', 'password']))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials',
                'debug' => [
                    'email' => $request->email,
                    'password' => $request->password,
                    'hashed_password_in_db' => User::where('email', $request->email)->value('password')
                ]
            ], 401);
        }


        // Retrieve authenticated user
        $user = Auth::user();

        // Check verification statuses
        if (is_null($user->email_verified_at) && is_null($user->mobile_verified_at)) {
            return response()->json([
                'status' => false,
                'message' => 'Email and mobile not verified',
                'errors' => ['Verification' => ['Both email and mobile are not verified']]
            ], 403); // 403 Forbidden for verification issues
        }

        if (is_null($user->email_verified_at)) {
            return response()->json([
                'status' => false,
                'message' => 'Email not verified',
                'errors' => ['Verification' => ['Email is not verified']]
            ], 403);
        }

        if (is_null($user->mobile_verified_at)) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile not verified',
                'errors' => ['Verification' => ['Mobile is not verified']]
            ], 403);
        }

        // Prepare response data
        $responseData = [
            'status' => true,
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'email_verified_at' => $user->email_verified_at,
            'mobile_verified_at' => $user->mobile_verified_at,
            'role' => $user->roles,
            'permissions' => $user->roles->first()?->permissions->pluck('name'), // Safely access permissions
            'message' => 'User logged in successfully',
            'token' => $user->createToken("API TOKEN")->plainTextToken,
        ];

        // Include profile status in response
        // $responseData['isProfileSet'] = $user->profile !== null;

        return response()->json($responseData, 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'An error occurred during login.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function loginMobile(Request $request){

        try {

            $validateUser = Validator::make($request->all(),
            [
                'mobile' => 'required|numeric',
                'password' => 'required'
            ]);

            if($validateUser->fails()){

                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            if(!Auth::attempt($request->only(['mobile', 'password']))){

                return response()->json([
                    'status' => false,
                    'message' => 'Mobile & Password does not match with our record.',
                    'errors' => ['Credentials' => ['Invalid mobile or password']]
                ], 401);
            }

            $user = User::where('mobile', $request->mobile)->first();

            if ($user->email_verified_at === null && $user->mobile_verified_at === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email and mobile not verified',
                    'errors' => ['Error' => ['Email and mobile not verified.']]
                ], 401);
            } elseif ($user->email_verified_at === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email not verified',
                    'errors' => ['Error' => ['Email not verified.']]
                ], 401);
            } elseif ($user->mobile_verified_at === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile not verified',
                    'errors' => ['Error' => ['Mobile not verified.']]
                ], 401);
            } else {
                $user->authentications;//Log user activities
            // $user->assignRole('Manager');
            // $user->roles()->detach();
            if($user->profile){
                return response()->json([
                    'status' => true,
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    //'isProfileSet' => true,
                    'email_verified_at' => $user->email_verified_at,
                    'mobile_verified_at' => $user->mobile_verified_at,
                    //'first_login' => $user->first_login,
                    //'profile_id' => $user->profile->profile_id,
                    'role' => $user->roles,
                    'permissions' => $user->roles[0]->permissions->pluck('name'),
                    'message' => 'User Logged In Successfully',
                    'token' => $user->createToken("API TOKEN")->plainTextToken
                ],200);
            }
            else{
                return response()->json([
                    'status' => true,
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    //'isProfileSet' => false,
                    //'first_login' => $user->first_login,
                    'email_verified_at' => $user->email_verified_at,
                    'mobile_verified_at' => $user->mobile_verified_at,
                    'role' => $user->roles,
                    'permissions' => $user->roles[0]->permissions->pluck('name'),
                    'message' => 'User Logged In Successfully',
                    'token' => $user->createToken("API TOKEN")->plainTextToken
                ],200);
            }
            }


        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
