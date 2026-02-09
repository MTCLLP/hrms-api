<?php

namespace Modules\RBAC\Http\Controllers\Api\Auth;

use Modules\RBAC\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Validation\Rules\Password as RulesPassword;
use App\Notifications\PasswordResetOTP;
// use App\Notifications\PasswordResetConfirmation;
//Implementing Laravel Queues
use App\Jobs\SendOTP;
use Illuminate\Support\Facades\Queue;


class PasswordResetController extends Controller
{

    /**
     * Send a password reset email to the user.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
            // Add more validation rules for other fields
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {

            return response()->json([
                'status' => false,
                'message' => 'User Not found',
                'errors' => ['email' => ['Email not found']]
            ], 404);
        }

        $token = Password::getRepository()->create($user);

        // Generate OTP
        $email_otp = mt_rand(100000, 999999);

        // Save the OTP to the user model
        $user->email_otp = $email_otp;
        $user->save();

        // Send OTP to the user's email
        Queue::push(new SendOTP($user, $email_otp));

        return response()->json(['message' => 'OTP sent on registered email'], 200);
    }

    /**
     * Reset the user's password.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'confirm_password' => 'required|same:password',
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Check if the entered OTP matches the one stored in the user model
        // if ($user->email_otp != $request->otp) {
        //     return response()->json([
        //         'status' => false,
        //         'errors' => ['otp' => ['Invalid OTP']]
        //     ], 401);
        // }

        // Update the user's password
        $user->password = $request->password;
        $user->save();

        // Reset the OTP
        // $user->email_otp = null;
        $user->save();

        // // Send a password reset confirmation email
        // Mail::to($user->email)->send(new PasswordResetConfirmation());

        return response()->json(['message' => 'Password reset successful.'], 200);
    }

}
