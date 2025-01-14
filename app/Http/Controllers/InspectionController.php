<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inspector;
use App\Models\Inspection;
use App\Models\Violation;

class InspectionController extends Controller
{
    public function getInspections(Request $request)
    {
        // Start with a base query
        $query = Inspection::with([
            'business',
            'business.owner',
            'inspector',
            'business.violations.violationDetails' // Eager load violation details
        ]);

        // Apply filters based on request parameters
        if ($request->has('inspection_date')) {
            $query->whereDate('inspection_date', $request->inspection_date);
        }

        if ($request->has('with_violations')) {
            // Filter inspections with violations
            $withViolations = $request->with_violations === 'yes';
            $query->whereHas('business.violations', function ($q) use ($withViolations) {
                if ($withViolations) {
                    $q->whereNotNull('violation_id'); // Ensure violations exist
                } else {
                    $q->whereNull('violation_id'); // No violations
                }
            });
        }

        if ($request->has('inspector_id')) {
            $query->where('inspector_id', $request->inspector_id);
        }

        // Add business name search
        if ($request->has('business_name') && !empty($request->business_name)) {
            $query->whereHas('business', function ($q) use ($request) {
                $q->where('business_name', 'LIKE', '%' . $request->business_name . '%');
            });
        }

        // Updated sort order handling
        if ($request->has('sort_order')) {
            $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';
            $query->orderBy('created_at', $direction);
        } else {
            // Default sorting if not specified
            $query->orderBy('created_at', 'desc');
        }

        // Get the current page from the request, default is 1
        $page = $request->input('page', 1);

        // Paginate the filtered results
        $inspections = $query->paginate(15, ['*'], 'page', $page);

        // Transform the inspections for consistent structure
        $inspections->getCollection()->transform(function ($inspection) {
            return [
                'inspection_id' => $inspection->inspection_id,
                'inspection_date' => $inspection->inspection_date,
                'type_of_inspection' => $inspection->type_of_inspection,
                'with_violations' => $inspection->business->violations->isNotEmpty(), // Check if there are violations
                'business_id' => $inspection->business_id,
                'inspector_id' => $inspection->inspector_id,
                'created_at' => $inspection->created_at,
                'updated_at' => $inspection->updated_at,
                'inspector' => [
                    'inspector_id' => $inspection->inspector->inspector_id,
                    'email' => $inspection->inspector->email,
                    'first_name' => $inspection->inspector->first_name,
                    'last_name' => $inspection->inspector->last_name
                ],
                'business' => [
                    'business_id' => $inspection->business->business_id,
                    'business_permit' => $inspection->business->business_permit,
                    'business_name' => $inspection->business->business_name,
                    'image_url' => $inspection->business->image_url,
                    'status' => $inspection->business->status,
                    'owner' => [
                        'business_owner_id' => $inspection->business->owner->business_owner_id,
                        'email' => $inspection->business->owner->email,
                        'first_name' => $inspection->business->owner->first_name,
                        'last_name' => $inspection->business->owner->last_name,
                        'phone_number' => $inspection->business->owner->phone_number
                    ]
                ],
                'violations' => $inspection->business->violations->map(function ($violation) {
                    // Access the violation details and map them
                    return [
                        'violation_id' => $violation->violation_id,
                        'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'), // Collect all violation details
                        'violation_receipt_no' => $violation->violation_receipt_no,
                        'violation_date' => $violation->violation_date,
                        'due_date' => $violation->due_date,
                        'status' => $violation->status
                    ];
                })
            ];
        });

        \Log::info('Inspections retrieved', $inspections->toArray());

        // Return the transformed data
        return response()->json([
            'status' => 200,
            'inspections' => $inspections
        ]);
    }



    public function getInspectionById($inspection_id)
    {
        // Start with a base query for fetching inspection data
        $inspection = Inspection::with([
            'business.owner',
            'inspector',
            'business.violations.violationDetails', // Eager load violation details
        ])
            ->where('inspection_id', $inspection_id) // Filter by inspection_id
            ->first();

        // Check if inspection exists
        if (!$inspection) {
            return response()->json(['message' => 'Inspection not found'], 404);
        }

        // Log inspection data
        \Log::info('Inspection retrieved', $inspection->toArray());

        // Map through the violations to extract the relevant fields
        $violations = $inspection->business->violations->map(function ($violation) {
            return [
                'violation_id' => $violation->violation_id,
                'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation')->toArray(), // Pluck all violation details
                'violation_receipt_no' => $violation->violation_receipt_no,
                'violation_date' => $violation->violation_date,
                'due_date' => $violation->due_date,
                'status' => $violation->status,
            ];
        });

        // Format inspection data in a similar way to getInspections
        $inspectionData = [
            'inspection_id' => $inspection->inspection_id,
            'inspection_date' => $inspection->inspection_date,
            'type_of_inspection' => $inspection->type_of_inspection,
            'with_violations' => $inspection->business->violations->isNotEmpty(),
            'business_id' => $inspection->business_id,
            'inspector_id' => $inspection->inspector_id,
            'created_at' => $inspection->created_at,
            'updated_at' => $inspection->updated_at,
            'inspector' => [
                'inspector_id' => $inspection->inspector->inspector_id,
                'email' => $inspection->inspector->email,
                'first_name' => $inspection->inspector->first_name,
                'last_name' => $inspection->inspector->last_name,
            ],
            'business' => [
                'business_id' => $inspection->business->business_id,
                'business_permit' => $inspection->business->business_permit,
                'business_name' => $inspection->business->business_name,
                'image_url' => $inspection->business->image_url,
                'status' => $inspection->business->status,
                'owner' => [
                    'business_owner_id' => $inspection->business->owner->business_owner_id,
                    'email' => $inspection->business->owner->email,
                    'first_name' => $inspection->business->owner->first_name,
                    'last_name' => $inspection->business->owner->last_name,
                    'phone_number' => $inspection->business->owner->phone_number,
                ]
            ],
            'violations' => $violations,
        ];

        // Return the response in a consistent format
        return response()->json([
            'status' => 200,
            'inspection' => $inspectionData,
        ]);
    }


    public function getCardInfo()
    {
        $total_inspectors = Inspector::count();
        $total_inspections = Inspection::sum('inspection_id');
        $total_violators = Inspection::where('with_violations', 1)->count();
        $overdue_violation = Violation::where('due_date', '<', now())->count();

        return response()->json([
            'total_inspectors' => $total_inspectors,
            'total_inspections' => $total_inspections,
            'total_violators' => $total_violators,
            'overdue_violation' => $overdue_violation,
        ]);
    }

    // todo work on this later (admin power)
    public function deleteInspection(Request $request)
    {
        $id = $request->inspection_id;
        $inspection = Inspection::find($id);
        $inspection->delete();
        return response(['message' => 'Inspection deleted']);
    }

    public function deleteAllInspection()
    {
        Inspection::truncate();
        return response(['message' => 'All inspections deleted']);
    }

    public function resolveViolation($violation_id)
    {
        $violation = Violation::find($violation_id);
        $violation->resolved = 1;
        $violation->save();

        return response()->json([
            'status' => 200,
            'message' => 'Violation resolved successfully',
            'violation' => $violation
        ]);
    }
}
