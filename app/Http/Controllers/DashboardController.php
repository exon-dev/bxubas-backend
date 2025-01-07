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
}
