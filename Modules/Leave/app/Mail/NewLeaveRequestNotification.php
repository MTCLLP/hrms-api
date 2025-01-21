<?php

namespace Modules\Leave\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use Modules\Leave\Models\LeaveRequest;
use Modules\Employee\Models\Employee;

class NewLeaveRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $leaveRequest;
    public $employee;

    public function __construct(LeaveRequest $leaveRequest, Employee $employee)
    {
        $this->leaveRequest = $leaveRequest;
        $this->employee = $employee;
    }

    public function build()
    {
        return $this->subject('New Leave Request Submitted')
            ->view('leave::leave_request_notification') // Use the correct view path
            ->with([
                'leaveRequest' => $this->leaveRequest,
                'employee' => $this->employee,
            ]);
    }
}
