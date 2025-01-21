<!DOCTYPE html>
<html>
<head>
    <title>Leave Request Approved</title>
</head>
<body>
    <p>Dear {{ $leave->employee->user->name }},</p>

    <p>Your leave request has been approved. Here are the details:</p>

    <ul>
        <li><strong>Start Date:</strong> {{ $leave->start_date }}</li>
        <li><strong>End Date:</strong> {{ $leave->end_date }}</li>
        <li><strong>Leave Type:</strong> {{ $leave->leaveType->type_name }}</li>
        <li><strong>Comments:</strong> {{ $leave->comments }}</li>
        <li><strong>Approved By:</strong> {{ $supervisor->name }}</li>
    </ul>

    <p>If you have any questions, please contact your manager.</p>

    <p>Thank you!</p>
</body>
</html>
