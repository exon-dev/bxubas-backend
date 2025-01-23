<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChartController extends Controller
{

    public function getChartData(Request $request)
    {
        $period = $request->input('period', 'month');
        $year = $request->input('year', date('Y'));
        $month = $request->input('month');

        // Define date format and grouping based on period
        $dateFormat = match ($period) {
            'day' => 'DATE(inspection_date)',
            'week' => 'YEARWEEK(inspection_date)',
            'month' => 'MONTH(inspection_date)',
        };

        $labelFormat = match ($period) {
            'day' => 'DATE_FORMAT(inspection_date, "%Y-%m-%d")',
            'week' => 'CONCAT("Week ", WEEK(inspection_date))',
            'month' => 'MONTHNAME(inspection_date)',
        };

        // For bar graph - inspections and violations count
        $inspectionsQuery = \App\Models\Inspection::selectRaw("COUNT(*) as total, {$labelFormat} as label")
            ->whereYear('inspection_date', $year);

        $violationsQuery = \App\Models\Violation::selectRaw("COUNT(*) as total, {$labelFormat} as label")
            ->whereYear('created_at', $year);

        // For pie graph - resolved vs total violations
        $resolvedViolations = \App\Models\Violation::where('status', 'resolved')
            ->whereYear('created_at', $year);
        $totalViolations = \App\Models\Violation::whereYear('created_at', $year);

        // For line graph - inspections with and without violations
        $inspectionsWithViolations = \App\Models\Inspection::has('violations')
            ->selectRaw("COUNT(*) as total, {$labelFormat} as label")
            ->whereYear('inspection_date', $year);

        $inspectionsWithoutViolations = \App\Models\Inspection::doesntHave('violations')
            ->selectRaw("COUNT(*) as total, {$labelFormat} as label")
            ->whereYear('inspection_date', $year);

        // Apply period-specific filters
        if ($period === 'day') {
            // Last 30 days if no month specified
            if (!$month) {
                $startDate = now()->subDays(30);
                $inspectionsQuery->where('inspection_date', '>=', $startDate);
                $violationsQuery->where('created_at', '>=', $startDate);
                $resolvedViolations->where('created_at', '>=', $startDate);
                $totalViolations->where('created_at', '>=', $startDate);
                $inspectionsWithViolations->where('inspection_date', '>=', $startDate);
                $inspectionsWithoutViolations->where('inspection_date', '>=', $startDate);
            } else {
                $inspectionsQuery->whereMonth('inspection_date', $month);
                $violationsQuery->whereMonth('created_at', $month);
                $resolvedViolations->whereMonth('created_at', $month);
                $totalViolations->whereMonth('created_at', $month);
                $inspectionsWithViolations->whereMonth('inspection_date', $month);
                $inspectionsWithoutViolations->whereMonth('inspection_date', $month);
            }
        } elseif ($period === 'week') {
            // Last 12 weeks if no month specified
            if (!$month) {
                $startDate = now()->subWeeks(12);
                $inspectionsQuery->where('inspection_date', '>=', $startDate);
                $violationsQuery->where('created_at', '>=', $startDate);
                $resolvedViolations->where('created_at', '>=', $startDate);
                $totalViolations->where('created_at', '>=', $startDate);
                $inspectionsWithViolations->where('inspection_date', '>=', $startDate);
                $inspectionsWithoutViolations->where('inspection_date', '>=', $startDate);
            } else {
                $inspectionsQuery->whereMonth('inspection_date', $month);
                $violationsQuery->whereMonth('created_at', $month);
                $resolvedViolations->whereMonth('created_at', $month);
                $totalViolations->whereMonth('created_at', $month);
                $inspectionsWithViolations->whereMonth('inspection_date', $month);
                $inspectionsWithoutViolations->whereMonth('inspection_date', $month);
            }
        } elseif ($month) {
            $inspectionsQuery->whereMonth('inspection_date', $month);
            $violationsQuery->whereMonth('created_at', $month);
            $resolvedViolations->whereMonth('created_at', $month);
            $totalViolations->whereMonth('created_at', $month);
            $inspectionsWithViolations->whereMonth('inspection_date', $month);
            $inspectionsWithoutViolations->whereMonth('inspection_date', $month);
        }

        // Group by the appropriate date format
        $inspectionsQuery->groupBy(\DB::raw($dateFormat));
        $violationsQuery->groupBy(\DB::raw($dateFormat));
        $inspectionsWithViolations->groupBy(\DB::raw($dateFormat));
        $inspectionsWithoutViolations->groupBy(\DB::raw($dateFormat));

        // Order by date
        $orderBy = $dateFormat;

        $inspectionsQuery->orderBy(\DB::raw($orderBy));
        $violationsQuery->orderBy(\DB::raw($orderBy));
        $inspectionsWithViolations->orderBy(\DB::raw($orderBy));
        $inspectionsWithoutViolations->orderBy(\DB::raw($orderBy));

        return response()->json([
            'barGraph' => [
                'inspections' => $inspectionsQuery->get(),
                'violations' => $violationsQuery->get()
            ],
            'pieGraph' => [
                'resolved' => $resolvedViolations->count(),
                'total' => $totalViolations->count()
            ],
            'lineGraph' => [
                'withViolations' => $inspectionsWithViolations->get(),
                'withoutViolations' => $inspectionsWithoutViolations->get()
            ]
        ]);
    }
}
