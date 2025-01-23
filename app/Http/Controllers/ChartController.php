<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Violation;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Support\Carbon;

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
        $inspectionsQuery = Inspection::selectRaw(
            "COUNT(*) as total,
        CONCAT('Week ', WEEK(inspection_date)) as label,
        YEARWEEK(inspection_date) as week"
        )
            ->whereYear('inspection_date', $year);

        // Join `violations` with `inspections` to access `inspection_date`
        $violationsQuery = Violation::join('inspections', 'violations.inspection_id', '=', 'inspections.inspection_id')
            ->selectRaw(
                "COUNT(*) as total,
            CONCAT('Week ', WEEK(inspection_date)) as label,
            YEARWEEK(inspection_date) as week"
            )
            ->whereYear('inspections.inspection_date', $year);

        // For pie graph - resolved vs total violations
        $resolvedViolations = Violation::where('status', 'resolved')
            ->whereYear('created_at', $year);
        $totalViolations = Violation::whereYear('created_at', $year);

        // For line graph - inspections with and without violations
        $inspectionsWithViolations = Inspection::has('violations')
            ->selectRaw(
                "COUNT(*) as total,
            CONCAT('Week ', WEEK(inspection_date)) as label,
            YEARWEEK(inspection_date) as week"
            )
            ->whereYear('inspection_date', $year);

        $inspectionsWithoutViolations = Inspection::doesntHave('violations')
            ->selectRaw(
                "COUNT(*) as total,
            CONCAT('Week ', WEEK(inspection_date)) as label,
            YEARWEEK(inspection_date) as week"
            )
            ->whereYear('inspection_date', $year);

        // Apply period-specific filters
        if ($period === 'day') {
            if (!$month) {
                $startDate = now()->subDays(30);
                $inspectionsQuery->where('inspection_date', '>=', $startDate);
                $violationsQuery->where('inspections.inspection_date', '>=', $startDate);
                $resolvedViolations->where('created_at', '>=', $startDate);
                $totalViolations->where('created_at', '>=', $startDate);
                $inspectionsWithViolations->where('inspection_date', '>=', $startDate);
                $inspectionsWithoutViolations->where('inspection_date', '>=', $startDate);
            } else {
                $inspectionsQuery->whereMonth('inspection_date', $month);
                $violationsQuery->whereMonth('inspections.inspection_date', $month);
                $resolvedViolations->whereMonth('created_at', $month);
                $totalViolations->whereMonth('created_at', $month);
                $inspectionsWithViolations->whereMonth('inspection_date', $month);
                $inspectionsWithoutViolations->whereMonth('inspection_date', $month);
            }
        } elseif ($period === 'week') {
            if (!$month) {
                $startDate = now()->subWeeks(12);
                $endDate = now()->endOfMonth();
                $inspectionsQuery->whereBetween('inspection_date', [$startDate, $endDate]);
                $violationsQuery->whereBetween('inspections.inspection_date', [$startDate, $endDate]);
                $resolvedViolations->whereBetween('created_at', [$startDate, $endDate]);
                $totalViolations->whereBetween('created_at', [$startDate, $endDate]);
                $inspectionsWithViolations->whereBetween('inspection_date', [$startDate, $endDate]);
                $inspectionsWithoutViolations->whereBetween('inspection_date', [$startDate, $endDate]);
            } else {
                $inspectionsQuery->whereMonth('inspection_date', $month);
                $violationsQuery->whereMonth('inspections.inspection_date', $month);
                $resolvedViolations->whereMonth('created_at', $month);
                $totalViolations->whereMonth('created_at', $month);
                $inspectionsWithViolations->whereMonth('inspection_date', $month);
                $inspectionsWithoutViolations->whereMonth('inspection_date', $month);
            }
        } elseif ($month) {
            $inspectionsQuery->whereMonth('inspection_date', $month);
            $violationsQuery->whereMonth('inspections.inspection_date', $month);
            $resolvedViolations->whereMonth('created_at', $month);
            $totalViolations->whereMonth('created_at', $month);
            $inspectionsWithViolations->whereMonth('inspection_date', $month);
            $inspectionsWithoutViolations->whereMonth('inspection_date', $month);
        }

        // Group by the appropriate week and label
        $inspectionsQuery->groupBy('week', 'label');
        $violationsQuery->groupBy('week', 'label');
        $inspectionsWithViolations->groupBy('week', 'label');
        $inspectionsWithoutViolations->groupBy('week', 'label');

        // Order by week
        $inspectionsQuery->orderBy('week');
        $violationsQuery->orderBy('week');
        $inspectionsWithViolations->orderBy('week');
        $inspectionsWithoutViolations->orderBy('week');

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



    public function getKPIData(Request $request)
    {
        $period = $request->input('period', 'month');
        $year = $request->input('year', date('Y'));
        $month = $request->input('month');

        // Get current period data
        $currentQuery = Inspection::query()->whereYear('inspection_date', $year);
        $previousQuery = Inspection::query()->whereYear('inspection_date', $year - 1);

        if ($month) {
            $currentQuery->whereMonth('inspection_date', $month);
            $previousQuery->whereMonth('inspection_date', $month);
        }

        $totalInspections = $currentQuery->count();
        $previousInspections = $previousQuery->count();

        $inspectionsWithViolations = $currentQuery->clone()->has('violations')->count();
        $violationRate = $totalInspections > 0 ? ($inspectionsWithViolations / $totalInspections) * 100 : 0;

        $currentViolations = Violation::whereHas('inspection', function ($q) use ($year, $month) {
            $q->whereYear('inspection_date', $year);
            if ($month) {
                $q->whereMonth('inspection_date', $month);
            }
        });

        $totalViolations = $currentViolations->count();
        $resolvedViolations = $currentViolations->clone()->where('violations.status', 'resolved')->count();
        $resolutionRate = $totalViolations > 0 ? ($resolvedViolations / $totalViolations) * 100 : 0;

        $currentAvgResolutionDays = $currentViolations->clone()
            ->where('violations.status', 'resolved')
            ->whereNotNull('violation_date')
            ->whereNotNull('due_date')
            ->selectRaw('AVG(DATEDIFF(due_date, violation_date)) as avg_days')
            ->value('avg_days') ?? 0;

        $previousViolations = Violation::whereHas('inspection', function ($q) use ($year, $month) {
            $q->whereYear('inspection_date', $year - 1);
            if ($month) {
                $q->whereMonth('inspection_date', $month);
            }
        });

        $previousTotalViolations = $previousViolations->count();
        $previousResolvedViolations = $previousViolations->clone()->where('violations.status', 'resolved')->count();
        $previousResolutionRate = $previousTotalViolations > 0
            ? ($previousResolvedViolations / $previousTotalViolations) * 100 : 0;

        $previousAvgResolutionDays = $previousViolations->clone()
            ->where('violations.status', 'resolved')
            ->whereNotNull('violation_date')
            ->whereNotNull('due_date')
            ->selectRaw('AVG(DATEDIFF(due_date, violation_date)) as avg_days')
            ->value('avg_days') ?? 0;

        // Trends
        $inspectionsTrend = $previousInspections > 0
            ? (($totalInspections - $previousInspections) / $previousInspections) * 100
            : 0;

        $previousViolationRate = $previousQuery->has('violations')->count() / max($previousQuery->count(), 1) * 100;
        $violationTrend = $previousViolationRate > 0
            ? ($violationRate - $previousViolationRate)
            : 0;

        $resolutionTrend = $previousResolutionRate > 0
            ? ($resolutionRate - $previousResolutionRate)
            : 0;

        $resolutionTimeTrend = $previousAvgResolutionDays > 0
            ? (($currentAvgResolutionDays - $previousAvgResolutionDays) / $previousAvgResolutionDays) * -100
            : 0;

        return response()->json([
            'totalInspections' => $totalInspections ?? 0,
            'violationRate' => round($violationRate ?? 0, 2),
            'resolutionRate' => round($resolutionRate ?? 0, 2),
            'avgResolutionDays' => round($currentAvgResolutionDays ?? 0, 1),
            'inspectionsTrend' => [
                'value' => round($inspectionsTrend ?? 0, 2),
                'status' => $inspectionsTrend >= 0 ? 'increase' : 'decrease',
            ],
            'violationTrend' => [
                'value' => round($violationTrend ?? 0, 2),
                'status' => $violationTrend >= 0 ? 'increase' : 'decrease',
            ],
            'resolutionTrend' => [
                'value' => round($resolutionTrend ?? 0, 2),
                'status' => $resolutionTrend >= 0 ? 'increase' : 'decrease',
            ],
            'resolutionTimeTrend' => [
                'value' => round($resolutionTimeTrend ?? 0, 2),
                'status' => $resolutionTimeTrend >= 0 ? 'increase' : 'decrease',
            ],
        ]);
    }



    /**
     * Get the date format and label format based on the period.
     */
    private function getDateFormatAndLabel(string $period): array
    {
        return match ($period) {
            'day' => ['DATE(inspection_date)', 'DATE_FORMAT(inspection_date, "%Y-%m-%d")'],
            'week' => ['YEARWEEK(inspection_date)', 'CONCAT("Week ", WEEK(inspection_date))'],
            'month' => ['MONTH(inspection_date)', 'MONTHNAME(inspection_date)'],
            default => throw new InvalidArgumentException("Invalid period: $period"),
        };
    }


}
