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

class LeaveApprovalNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $leave;
    public $supervisor;

    /**
     * Create a new message instance.
     *
     * @param  $leave
     * @param  $supervisor
     */
    public function __construct($leave, $supervisor)
    {
        $this->leave = $leave;
        $this->supervisor = $supervisor;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('leave::leave_approval_notification') // Update path if needed
            ->subject('Your Leave Request has been Approved')
            ->with([
                'leave' => $this->leave,
                'supervisor' => $this->supervisor,
            ]);
    }
}
