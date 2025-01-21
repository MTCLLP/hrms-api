<!DOCTYPE html>
<html>
<head>
    <title>New Leave Request</title>
</head>
<body>
    {{-- @foreach ($leaveRequest->employee->superiors as $superior)
        <p>Dear {{ $superior->superior_details->user }},</p>
    @endforeach --}}
    <p>Greetings!</p>
    <p>{{ $leaveRequest->employee->user->name }} has submitted a new leave request with the following details:</p>

    <ul>
        <li><strong>Start Date:</strong> {{ $leaveRequest->start_date }}</li>
        <li><strong>End Date:</strong> {{ $leaveRequest->end_date }}</li>
        <li><strong>Leave Type:</strong> {{ $leaveRequest->leaveType->type_name }}</li>
        <li><strong>Is Half Day:</strong> {{ $leaveRequest->is_half_day ? 'Yes' : 'No' }}</li>
    </ul>

    <p>Please log in to the system to review and take necessary action.</p>

    <p>Thank you!</p>
</body>
</html>
