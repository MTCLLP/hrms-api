<?php

namespace Modules\RBAC\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\RBAC\Notifications\SendOtpNotification;
use Modules\RBAC\Events\UserRegistered;
use Modules\RBAC\Models\Otp;

class SendOTPAfterUserRegistered
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event)
    {
        $user = $event->user;

        // Generate OTP
        $otp = rand(100000, 999999);
        $expiry = now()->addMinutes(10);

        // Save OTP to the database
        OTP::create([
            'user_id' => $user->id,
            'otp_type' => 'email', // Or 'mobile' depending on your logic
            'otp' => Hash::make($otp),
            'expires_at' => $expiry,
        ]);

        // Send OTP via email or SMS
        Notification::send($user, new SendOtpNotification($otp));
    }
}
