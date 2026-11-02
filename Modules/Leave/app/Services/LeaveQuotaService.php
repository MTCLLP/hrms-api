<?php

namespace Modules\Leave\Services;

use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Illuminate\Support\Facades\Schema;

class LeaveQuotaService
{
    /**
     * Preference definitions.
     * Normalized lowercase keywords for identifying leave type categories.
     * 1. Restricted Holiday
     * 2. Casual or Earned Leave
     * 3. Sick Leave
     */
    protected static array $preferences = [
        1 => ['restricted holiday', 'restricted', 'rh'],
        2 => ['casual leave', 'casual', 'earned leave', 'earned', 'privilege leave', 'privilege', 'cl', 'el', 'pl'],
        3 => ['sick leave', 'sick', 'medical leave', 'medical', 'sl', 'ml'],
    ];

    /**
     * Determine preference tier of a leave type name.
     */
    public static function getPreferenceTier(string $typeName): int
    {
        $normalized = strtolower(trim($typeName));
        foreach (self::$preferences as $tier => $keywords) {
            foreach ($keywords as $keyword) {
                if ($normalized === $keyword || str_contains($normalized, $keyword)) {
                    return $tier;
                }
            }
        }
        return 99; // Other leave types
    }

    /**
     * Calculate deductions across quotas using preference order and deduction split.
     *
     * @param int $employeeId
     * @param int $primaryLeaveTypeId
     * @param float $requestedDays
     * @return array
     */
    public static function calculateDeductions(int $employeeId, int $primaryLeaveTypeId, float $requestedDays): array
    {
        $query = LeaveBalance::with('leaveType')->where('employee_id', $employeeId);
        if (Schema::hasColumn('leave_balances', 'is_active')) {
            $query->where('is_active', 1);
        }
        $balances = $query->get();

        $deductions = [];
        $needed = (float)$requestedDays;

        // 1. First, check the primary requested leave type
        $primaryBalance = $balances->firstWhere('leavetype_id', $primaryLeaveTypeId);
        $primaryAvail = $primaryBalance ? max(0.0, (float)$primaryBalance->balance_amount) : 0.0;

        if ($primaryAvail >= $needed) {
            // Primary balance is completely sufficient - no fallback needed
            return [
                'success' => true,
                'deductions' => [
                    [
                        'leavetype_id' => $primaryLeaveTypeId,
                        'type_name' => $primaryBalance->leaveType->type_name ?? 'Applied Leave',
                        'days' => $needed,
                    ]
                ],
                'total_allocated' => $needed,
                'shortfall' => 0.0,
            ];
        }

        // If primary has partial balance, use it first
        if ($primaryAvail > 0) {
            $deductions[] = [
                'leavetype_id' => $primaryLeaveTypeId,
                'type_name' => $primaryBalance->leaveType->type_name ?? 'Applied Leave',
                'days' => $primaryAvail,
            ];
            $needed -= $primaryAvail;
        }

        // 2. Sort remaining balances by preference order:
        // Tier 1: Restricted Holiday
        // Tier 2: Casual or Earned
        // Tier 3: Sick Leave
        // Tier 99: Others
        $remainingBalances = $balances->filter(function ($b) use ($primaryLeaveTypeId) {
            return $b->leavetype_id != $primaryLeaveTypeId && (float)$b->balance_amount > 0;
        })->sort(function ($a, $b) {
            $tierA = self::getPreferenceTier($a->leaveType->type_name ?? '');
            $tierB = self::getPreferenceTier($b->leaveType->type_name ?? '');
            if ($tierA === $tierB) {
                return (float)$b->balance_amount <=> (float)$a->balance_amount;
            }
            return $tierA <=> $tierB;
        });

        foreach ($remainingBalances as $balance) {
            if ($needed <= 0.0001) {
                break;
            }
            $avail = max(0.0, (float)$balance->balance_amount);
            if ($avail <= 0.0001) {
                continue;
            }

            $take = min($avail, $needed);
            $deductions[] = [
                'leavetype_id' => $balance->leavetype_id,
                'type_name' => $balance->leaveType->type_name ?? 'Leave',
                'days' => $take,
            ];
            $needed -= $take;
        }

        $allocated = (float)$requestedDays - max(0.0, $needed);

        return [
            'success' => $needed <= 0.0001,
            'deductions' => $deductions,
            'total_allocated' => $allocated,
            'shortfall' => max(0.0, round($needed, 2)),
        ];
    }

    /**
     * Apply the calculated deductions to employee balances.
     *
     * @param array $deductions
     * @param int $employeeId
     * @return void
     */
    public static function applyDeductions(array $deductions, int $employeeId): void
    {
        foreach ($deductions as $item) {
            $balance = LeaveBalance::where('employee_id', $employeeId)
                ->where('leavetype_id', $item['leavetype_id'])
                ->first();

            if ($balance) {
                $balance->balance_amount = (float)$balance->balance_amount - (float)$item['days'];
                $balance->save();
            }
        }
    }

    /**
     * Revert deductions when a leave is rejected, deleted, or approved without pay.
     *
     * @param LeaveRequest $leaveRequest
     * @param float $numberOfDays
     * @return void
     */
    public static function revertDeductions(LeaveRequest $leaveRequest, float $numberOfDays): void
    {
        $hasDeductions = Schema::hasColumn('leave_requests', 'deductions');
        $deductions = $hasDeductions ? $leaveRequest->deductions : null;

        if (is_array($deductions) && !empty($deductions)) {
            foreach ($deductions as $item) {
                $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                    ->where('leavetype_id', $item['leavetype_id'])
                    ->first();

                if ($balance) {
                    $balance->balance_amount = (float)$balance->balance_amount + (float)$item['days'];
                    $balance->save();
                }
            }
        } else {
            // Fallback for legacy records that didn't have deduction breakdown stored
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leavetype_id', $leaveRequest->leavetype_id)
                ->first();

            if ($balance) {
                $balance->balance_amount = (float)$balance->balance_amount + $numberOfDays;
                $balance->save();
            }
        }

        $hasIsDeducted = Schema::hasColumn('leave_requests', 'is_deducted');
        if ($hasIsDeducted) {
            $leaveRequest->is_deducted = false;
            if ($hasDeductions) {
                $leaveRequest->deductions = null;
            }
            $leaveRequest->save();
        }
    }

    /**
     * Format deductions into a human-readable summary string.
     *
     * @param array $deductions
     * @return string
     */
    public static function formatDeductionSummary(array $deductions): string
    {
        $parts = [];
        foreach ($deductions as $item) {
            $parts[] = "{$item['days']} day(s) from {$item['type_name']}";
        }
        return implode(', ', $parts);
    }
}
