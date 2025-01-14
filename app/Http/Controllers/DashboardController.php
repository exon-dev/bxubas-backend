<?php

namespace App\Http\Controllers;

use App\Models\Inspector;
use App\Models\Inspection;
use App\Models\Violation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getCardInfo()
    {
        $total_inspectors = Inspector::count();
        $total_inspections = Inspection::count();
        $total_violations = Violation::count();
        $overdue_violations = Violation::where('due_date', '<', now())->count();

        return response()->json([
            'total_inspectors' => $total_inspectors,
            'total_inspections' => $total_inspections,
            'total_violations' => $total_violations,
            'overdue_violations' => $overdue_violations
        ]);
    }

    // todo modify this with structure of inspected business

    public function getUpcomingDueDates()
    {
        $upcoming_due_dates = Violation::where('due_date', '>', now()->addDays(3))->get();

        return response()->json([
            'upcoming_due_dates' => $upcoming_due_dates
        ]);
    }

    public function getOverdueViolations()
    {
        $overdue_violations = Violation::where('due_date', '<', now())->get();

        return response()->json([
            'overdue_violations' => $overdue_violations
        ]);
    }
}
