<?php

use Modules\RBAC\Models\OTP;
use Modules\RBAC\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OTPController extends Controller
{
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp_type' => 'required|in:email,mobile',
            'otp' => 'required',
        ]);

        $otp = OTP::where('user_id', $request->user_id)
            ->where('otp_type', $request->otp_type)
            ->where('otp', $request->otp)
            ->valid()
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $user = User::find($request->user_id);

        if ($request->otp_type == 'email') {
            $user->email_verified_at = now();
        } else {
            $user->mobile_verified_at = now();
        }

        $user->save();

        $otp->delete();

        return response()->json(['message' => 'OTP verified successfully.']);
    }

}

