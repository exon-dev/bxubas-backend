<?php

namespace App\Http\Controllers;

use App\Models\Inspector;
use App\Models\Inspection;
use App\Models\Violation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getBellNotifDetails()
    {
        // Fetch inspections with violations for today
        $inspections = Inspection::with(['inspector', 'violations.violationDetails'])
            ->where('with_violations', true) // Use boolean true for clarity
            ->whereDate('inspection_date', now()->toDateString())
            ->get()
            ->map(function ($inspection) {
                return [
                    'inspector_name' => $inspection->inspector
                        ? trim($inspection->inspector->first_name . ' ' . $inspection->inspector->last_name)
                        : 'Inspector Deleted',

                    'inspection_date' => $inspection->inspection_date,
                    'type_of_inspection' => $inspection->type_of_inspection,
                    'violations' => $inspection->violations->map(function ($violation) {
                        return [
                            'receipt_no' => $violation->violation_receipt_no,
                            'details' => $violation->violationDetails->pluck('nature_of_violation')->toArray(), // Ensure array format
                        ];
                    })->toArray(), // Convert collection to array
                ];
            });

        // Return JSON response with notifications
        return response()->json([
            'notifications' => $inspections,
        ]);
    }


    public function getCardInfo()
    {
        $total_inspectors = Inspector::count(); // Count all inspectors
        $total_inspections = Inspection::count();
        // $total_violations = Inspection::where('with_violations', true)->count(); // Count inspections with violations
        $total_resolved_inspections = Violation::where('status', 'resolved')->count();
        $total_violations = Violation::whereHas('inspection', function ($query) {
            $query->where('with_violations', true);
        })->count(); // Count violations tied to inspections with violations
        $overdue_violations = Violation::whereHas('inspection', function ($query) {
            $query->where('with_violations', true);
        })->where('due_date', '<', now())->count(); // Count overdue violations tied to inspections with violations

        return response()->json([
            'total_inspectors' => $total_inspectors,
            'total_inspections' => $total_inspections,
            'total_resolved_inspections' => $total_resolved_inspections,
            'total_violations' => $total_violations,
            'overdue_violations' => $overdue_violations
        ]);
    }


    public function getCardInfoByInspector(Request $request)
    {
        // Validate inspector_id is provided
        $request->validate([
            'inspector_id' => 'required|exists:inspectors,inspector_id',
        ]);

        $inspectorId = $request->inspector_id;

        $total_inspections = Inspection::where('inspector_id', $inspectorId)->count(); // Count inspections for the specific inspector
        $total_resolved_inspections = Violation::where('status', 'resolved')
            ->whereHas('inspection', function ($query) use ($inspectorId) {
                $query->where('inspector_id', $inspectorId);
            })->count(); // Count resolved inspections for the specific inspector
        $total_violations = Violation::whereHas('inspection', function ($query) use ($inspectorId) {
            $query->where('inspector_id', $inspectorId)
                ->where('with_violations', true);
        })->count(); // Count violations tied to inspections with violations for the specific inspector
        $overdue_violations = Violation::whereHas('inspection', function ($query) use ($inspectorId) {
            $query->where('inspector_id', $inspectorId)
                ->where('with_violations', true);
        })->where('due_date', '<', now())->count(); // Count overdue violations tied to inspections with violations for the specific inspector

        return response()->json([
            'total_inspections' => $total_inspections,
            'total_resolved_inspections' => $total_resolved_inspections,
            'total_violations' => $total_violations,
            'overdue_violations' => $overdue_violations
        ]);
    }


    public function violators(Request $request)
    {
        // Start with a base query
        $query = Violation::with([
            'inspection.business.owner',
            'inspection.inspector',
        'violationDetails'
        ])->whereHas('inspection', function ($query) {
            $query->where('with_violations', true);
        });

        // Add business name search
        if ($request->has('business_name') && !empty($request->business_name)) {
            $query->whereHas('inspection.business', function ($q) use ($request) {
                $q->where('business_name', 'LIKE', '%' . $request->business_name . '%');
            });
        }

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sort order handling
        if ($request->has('sort_order')) {
            $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';
            $query->orderBy('created_at', $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Paginate results
        $violations = $query->paginate(15);

        // Transform the data
        $violations->getCollection()->transform(function ($violation) {
            return [
                'violation_id' => $violation->violation_id,
                'violation_receipt_no' => $violation->violation_receipt_no,
                'violation_date' => $violation->violation_date,
                'due_date' => $violation->due_date,
                'status' => $violation->status,
                'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'),
                'inspection' => [
                    'inspection_id' => $violation->inspection->inspection_id,
                    'inspection_date' => $violation->inspection->inspection_date,
                    'type_of_inspection' => $violation->inspection->type_of_inspection,
                    'inspector' => [
                        'inspector_id' => $violation->inspection->inspector->inspector_id,
                        'email' => $violation->inspection->inspector->email,
                        'first_name' => $violation->inspection->inspector->first_name,
                        'last_name' => $violation->inspection->inspector->last_name
                    ],
                    'business' => [
                        'business_id' => $violation->inspection->business->business_id,
                        'business_name' => $violation->inspection->business->business_name,
                        'business_permit' => $violation->inspection->business->business_permit,
                        'image_url' => $violation->inspection->business->image_url,
                        'status' => $violation->inspection->business->status,
                        'owner' => [
                            'business_owner_id' => $violation->inspection->business->owner->business_owner_id,
                            'email' => $violation->inspection->business->owner->email,
                            'first_name' => $violation->inspection->business->owner->first_name,
                            'last_name' => $violation->inspection->business->owner->last_name,
                            'phone_number' => $violation->inspection->business->owner->phone_number
                        ]
                    ]
                ]
            ];
        });

        return response()->json([
            'status' => 200,
            'violators' => $violations
        ]);
    }
}
