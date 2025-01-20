<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Violation;
use App\Models\Inspection;

class ViolationController extends Controller
{

    public function getViolators(Request $request)
    {
        // Base query to fetch violations with relationships
        $query = Inspection::with([
            'business',
            'business.owner',
            'inspector',
            'business.violations.violationDetails'
        ])
            ->where('with_violations', 1);

        // Add filters
        if ($request->filled('business_name')) {
            $query->whereHas('business', function ($q) use ($request) {
                $q->where('business_name', 'LIKE', '%' . $request->business_name . '%');
            });
        }

        if ($request->filled('status')) {
            $query->whereHas('business.violations', function ($q) use ($request) {
                $q->where('status', $request->status);
            });
        }

        // Sort order handling
        $direction = $request->get('sort_order', 'desc');
        $query->orderBy('created_at', $direction);

        $inspections = $query->paginate(15);

        \Log::info($inspections->toArray());

        // Transform the data
        $inspections->getCollection()->transform(function ($inspection) {
            return [
                'inspection_id' => $inspection->inspection_id,
                'inspection_date' => $inspection->inspection_date,
                'type_of_inspection' => $inspection->type_of_inspection,
                'with_violations' => true,
                'business_id' => $inspection->business_id,
                'inspector_id' => $inspection->inspector_id,
                'created_at' => $inspection->created_at,
                'updated_at' => $inspection->updated_at,
                'image_url' => $inspection->image_url, // Moved here from business
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
                        'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'),
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


    public function resolveViolation($inspection_id)
    {
        // Find the violation using the inspection_id
        $violation = Violation::where('inspection_id', $inspection_id)->first();

        // Log the violation resolution attempt
        \Log::info('Attempting to resolve violation for inspection ID: ' . $inspection_id, [
            'violation_id' => $violation ? $violation->violation_id : null,
            'violation_status' => $violation ? $violation->status : null
        ]);

        // Check if the violation exists
        if (!$violation) {
            return response()->json([
                'status' => 404,
                'message' => 'No violation found for the given inspection ID'
            ], 404);
        }

        // Delete associated violation details
        // $violation->violationDetails()->delete();

        // Update violation status to resolved
        $violation->status = 'resolved';

        // Update the with_violation in inspections table
        \DB::table('inspections')
            ->where('inspection_id', $inspection_id)
            ->update(['with_violations' => false]);

        $violation->save();

        return response()->json([
            'status' => 200,
            'message' => 'Violation resolved successfully',
            'violation' => $violation
        ]);
    }
}
