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
        $query = Inspection::with(['business', 'business.owner', 'inspector', 'business.violations']);

        // Apply filters based on request parameters
        if ($request->has('inspection_date')) {
            $query->whereDate('inspection_date', $request->inspection_date);
        }

        if ($request->has('with_violations')) {
            $withViolations = $request->with_violations === 'yes';
            $query->where('with_violations', $withViolations);
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
                'with_violations' => $inspection->with_violations,
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
                    return [
                        'violation_id' => $violation->violation_id,
                        'nature_of_violation' => $violation->nature_of_violation,
                        'violation_receipt_no' => $violation->violation_receipt_no,
                        'violation_date' => $violation->violation_date,
                        'due_date' => $violation->due_date,
                        'status' => $violation->status
                    ];
                })
            ];
        });

        return response()->json([
            'status' => 200,
            'inspections' => $inspections
        ]);
    }

    public function getInspectionById($inspection_id)
    {
        // Fetch the inspection with its related data (including violations)
        $inspection = Inspection::with([
            'business.owner',
            'inspector',
            'violations', // Directly eager load violations
        ])
            ->where('inspection_id', $inspection_id) // Use the inspection_id from the URL
            ->first();

        if (!$inspection) {
            return response()->json(['message' => 'Inspection not found'], 404);
        }


        \Log::info('Inspection retrieved', $inspection->toArray());
        $violations = $inspection->violations;
        \Log::info('Violation retrieved', ['violations' => $violations]);

        // Map through the violations and format the data
        $violations = $inspection->violations->map(function ($violation) {
            return [
                'violation_id' => $violation->violation_id,
                'nature_of_violation' => $violation->nature_of_violation,
                'violation_receipt_no' => $violation->violation_receipt_no,
                'violation_date' => $violation->violation_date,
                'due_date' => $violation->due_date,
                'status' => $violation->status,
            ];
        });

        // Format the response data
        $inspectionData = [
            'inspection_id' => $inspection->inspection_id,
            'inspection_date' => $inspection->inspection_date,
            'type_of_inspection' => $inspection->type_of_inspection,
            'with_violations' => $inspection->with_violations,
            'business' => [
                'business_id' => $inspection->business->business_id,
                'business_name' => $inspection->business->business_name,
                'image_url' => $inspection->business->image_url,
                'status' => $inspection->business->status,
                'owner' => [
                    'business_owner_id' => $inspection->business->owner->business_owner_id,
                    'first_name' => $inspection->business->owner->first_name,
                    'last_name' => $inspection->business->owner->last_name,
                    'email' => $inspection->business->owner->email,
                    'phone_number' => $inspection->business->owner->phone_number,
                ],
            ],
            'inspector' => [
                'inspector_id' => $inspection->inspector->inspector_id,
                'first_name' => $inspection->inspector->first_name,
                'last_name' => $inspection->inspector->last_name,
                'email' => $inspection->inspector->email,
            ],
            'violations' => $violations,
        ];

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
