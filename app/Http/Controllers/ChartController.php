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
        // Get filter parameters
        $period = $request->input('period', 'month');
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');
        $status = $request->input('status', 'all');
        $month = $request->input('month');
        $year = $request->input('year', date('Y'));

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

        // Base queries
        $inspectionsQuery = Inspection::selectRaw(
            "COUNT(*) as total,
        {$labelFormat} as label,
        {$dateFormat} as period_group"
        );

        $violationsQuery = Violation::join('inspections', 'violations.inspection_id', '=', 'inspections.inspection_id')
            ->selectRaw(
                "COUNT(*) as total,
        {$labelFormat} as label,
        {$dateFormat} as period_group"
            );

        $resolvedViolations = Violation::query();
        $totalViolations = Violation::query();

        $inspectionsWithViolations = Inspection::has('violations')
            ->selectRaw(
                "COUNT(*) as total,
        {$labelFormat} as label,
        {$dateFormat} as period_group"
            );

        $inspectionsWithoutViolations = Inspection::doesntHave('violations')
            ->selectRaw(
                "COUNT(*) as total,
        {$labelFormat} as label,
        {$dateFormat} as period_group"
            );

        // Apply month filter if provided
        if ($month) {
            $dateQueries = [$inspectionsQuery, $violationsQuery, $inspectionsWithViolations, $inspectionsWithoutViolations];
            foreach ($dateQueries as $query) {
                $query->whereMonth('inspection_date', $month)
                    ->whereYear('inspection_date', $year);
            }

            $resolvedViolations->whereHas('inspection', function ($query) use ($month, $year) {
                $query->whereMonth('inspection_date', $month)
                    ->whereYear('inspection_date', $year);
            });

            $totalViolations->whereHas('inspection', function ($query) use ($month, $year) {
                $query->whereMonth('inspection_date', $month)
                    ->whereYear('inspection_date', $year);
            });
        }
        // Apply date range filters if provided
        elseif ($startDate && $endDate) {
            $dateQueries = [$inspectionsQuery, $violationsQuery, $inspectionsWithViolations, $inspectionsWithoutViolations];
            foreach ($dateQueries as $query) {
                $query->whereBetween('inspection_date', [$startDate, $endDate]);
            }

            $resolvedViolations->whereHas('inspection', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspection_date', [$startDate, $endDate]);
            });

            $totalViolations->whereHas('inspection', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inspection_date', [$startDate, $endDate]);
            });
        } else {
            // Default date range based on period if no specific dates provided
            $defaultStartDate = match ($period) {
                'day' => now()->subDays(30),
                'week' => now()->subWeeks(12),
                'month' => now()->startOfYear(),
            };

            $dateQueries = [$inspectionsQuery, $violationsQuery, $inspectionsWithViolations, $inspectionsWithoutViolations];
            foreach ($dateQueries as $query) {
                $query->where('inspection_date', '>=', $defaultStartDate);
            }

            $resolvedViolations->whereHas('inspection', function ($query) use ($defaultStartDate) {
                $query->where('inspection_date', '>=', $defaultStartDate);
            });

            $totalViolations->whereHas('inspection', function ($query) use ($defaultStartDate) {
                $query->where('inspection_date', '>=', $defaultStartDate);
            });
        }

        // Apply status filter if provided
        if ($status !== 'all') {
            $violationsQuery->where('violations.status', $status);
            $resolvedViolations->where('status', $status);
            $totalViolations->where('status', $status);

            if ($status === 'resolved') {
                $inspectionsWithViolations->whereHas('violations', function ($query) {
                    $query->where('status', 'resolved');
                });
            } else {
                $inspectionsWithViolations->whereHas('violations', function ($query) {
                    $query->where('status', 'pending');
                });
            }
        }

        // Group by period
        $groupQueries = [$inspectionsQuery, $violationsQuery, $inspectionsWithViolations, $inspectionsWithoutViolations];
        foreach ($groupQueries as $query) {
            $query->groupBy('period_group', 'label')
                ->orderBy('period_group');
        }

        return response()->json([
            'barGraph' => [
                'inspections' => $inspectionsQuery->get(),
                'violations' => $violationsQuery->get()
            ],
            'pieGraph' => [
                'resolved' => $resolvedViolations->where('status', 'resolved')->count(),
                'pending' => $resolvedViolations->where('status', 'pending')->count(),
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
