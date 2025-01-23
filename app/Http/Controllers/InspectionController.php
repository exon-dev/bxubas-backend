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
            'business.owner',
            'inspector',
            'business.violations.violationDetails' // Eager load violation details
        ]);

        // Add business name search
        if ($request->has('business_name') && !empty($request->business_name)) {
            $query->whereHas('business', function ($q) use ($request) {
                $q->where('business_name', 'LIKE', '%' . $request->business_name . '%');
            });
        }
        // Add violation receipt number search
        if ($request->has('violation_receipt_no') && !empty($request->violation_receipt_no)) {
            $query->whereHas('business.violations', function ($q) use ($request) {
                $q->where('violation_receipt_no', 'LIKE', '%' . $request->violation_receipt_no . '%');
            });
        }

        // Apply filters based on request parameters
        if ($request->has('type_of_inspection')) {
            $query->where('type_of_inspection', $request->type_of_inspection);
        }

        if ($request->has('inspection_date')) {
            $query->whereDate('inspection_date', $request->inspection_date);
        }

        if ($request->has('with_violations')) {
            $withViolations = $request->with_violations === 'yes';
            $query->where('with_violations', $withViolations);
        }

        // Add inspector filter
        if ($request->has('inspector_id') && !empty($request->inspector_id)) {
            $query->where('inspector_id', $request->inspector_id);
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
                        'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'), // Collect all violation details
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
        // Start with a base query for fetching inspection data
        $inspection = Inspection::with([
            'business.owner',
            'business.address', // Added address relation
            'inspector',
            'business.violations.violationDetails', // Eager load violation details
        ])
            ->where('inspection_id', $inspection_id) // Filter by inspection_id
            ->first();

        // Check if inspection exists
        if (!$inspection) {
            return response()->json(['message' => 'Inspection not found'], 404);
        }

        // Filter violations specific to this inspection
        $violations = $inspection->business->violations
            ->filter(function ($violation) use ($inspection) {
                return $violation->inspection_id === $inspection->inspection_id;
            })
            ->map(function ($violation) {
                return [
                    'violation_id' => $violation->violation_id,
                    'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation')->toArray(),
                    'violation_receipt_no' => $violation->violation_receipt_no,
                    'violation_date' => $violation->violation_date,
                    'due_date' => $violation->due_date,
                    'status' => $violation->status,
                ];
            });

        // Format inspection data in a similar way to getInspections
        $inspectionData = [
            'inspection_id' => $inspection->inspection_id,
            'image_url' => $inspection->image_url,
            'inspection_date' => $inspection->inspection_date,
            'type_of_inspection' => $inspection->type_of_inspection,
            'with_violations' => $violations->isNotEmpty(),
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
                'status' => $inspection->business->status,
                'address' => [
                    'street_address' => $inspection->business->address->street,
                    'city' => $inspection->business->address->city,
                    'postal_code' => $inspection->business->address->zip,
                ],
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

    // public function getInspectionsWithViolations(Request $request)
    // {
    //     // Start with a base query
    //     $query = Inspection::with([
    //         'business',
    //         'business.owner',
    //         'inspector',
    //         'business.violations.violationDetails' // Eager load violation details
    //     ])->whereHas('business.violations', function ($q) {
    //         $q->whereNotNull('violation_id'); // Ensure violations exist
    //     });

    //     // Apply filters based on request parameters
    //     if ($request->has('inspection_date')) {
    //         $query->whereDate('inspection_date', $request->inspection_date);
    //     }

    //     if ($request->has('inspector_id')) {
    //         $query->where('inspector_id', $request->inspector_id);
    //     }

    //     // Add business name search
    //     if ($request->has('business_name') && !empty($request->business_name)) {
    //         $query->whereHas('business', function ($q) use ($request) {
    //             $q->where('business_name', 'LIKE', '%' . $request->business_name . '%');
    //         });
    //     }

    //     // Updated sort order handling
    //     if ($request->has('sort_order')) {
    //         $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';
    //         $query->orderBy('created_at', $direction);
    //     } else {
    //         // Default sorting if not specified
    //         $query->orderBy('created_at', 'desc');
    //     }

    //     // Get the current page from the request, default is 1
    //     $page = $request->input('page', 1);

    //     // Paginate the filtered results
    //     $inspections = $query->paginate(10, ['*'], 'page', $page);

    //     // Transform the inspections for consistent structure
    //     $inspections->getCollection()->transform(function ($inspection) {
    //         return [
    //             'inspection_id' => $inspection->inspection_id,
    //             'inspection_date' => $inspection->inspection_date,
    //             'type_of_inspection' => $inspection->type_of_inspection,
    //             'with_violations' => $inspection->business->violations->isNotEmpty(), // Check if there are violations
    //             'business_id' => $inspection->business_id,
    //             'inspector_id' => $inspection->inspector_id,
    //             'created_at' => $inspection->created_at,
    //             'updated_at' => $inspection->updated_at,
    //             'inspector' => [
    //                 'inspector_id' => $inspection->inspector->inspector_id,
    //                 'email' => $inspection->inspector->email,
    //                 'first_name' => $inspection->inspector->first_name,
    //                 'last_name' => $inspection->inspector->last_name
    //             ],
    //             'business' => [
    //                 'business_id' => $inspection->business->business_id,
    //                 'business_permit' => $inspection->business->business_permit,
    //                 'business_name' => $inspection->business->business_name,
    //                 'image_url' => $inspection->business->image_url,
    //                 'status' => $inspection->business->status,
    //                 'owner' => [
    //                     'business_owner_id' => $inspection->business->owner->business_owner_id,
    //                     'email' => $inspection->business->owner->email,
    //                     'first_name' => $inspection->business->owner->first_name,
    //                     'last_name' => $inspection->business->owner->last_name,
    //                     'phone_number' => $inspection->business->owner->phone_number
    //                 ]
    //             ],
    //             'violations' => $inspection->business->violations->map(function ($violation) {
    //                 // Access the violation details and map them
    //                 return [
    //                     'violation_id' => $violation->violation_id,
    //                     'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'), // Collect all violation details
    //                     'violation_receipt_no' => $violation->violation_receipt_no,
    //                     'violation_date' => $violation->violation_date,
    //                     'due_date' => $violation->due_date,
    //                     'status' => $violation->status
    //                 ];
    //             })
    //         ];
    //     });

    //     \Log::info('Inspections with violations retrieved', $inspections->toArray());

    //     // Return the transformed data
    //     return response()->json([
    //         'status' => 200,
    //         'inspections' => $inspections
    //     ]);
    // }
    // public function getUpcomingDues(Request $request)
    // {
    //     // Base query to fetch inspections with violations and relationships
    //     $query = Inspection::with([
    //         'business',
    //         'business.owner',
    //         'inspector',
    //         'business.violations.violationDetails'
    //     ])
    //         ->where('with_violations', 1) // Only get inspections with violations
    //         ->whereHas('business.violations', function ($q) {
    //             $q->where('status', 'pending')
    //                 ->where('due_date', '>', now())
    //                 ->where('due_date', '<=', now()->addDays(3));
    //         });

    //     if ($request->filled('inspection_id')) {
    //         $query->where('inspection_id', $request->inspection_id);
    //     }

    //     if ($request->filled('inspection_date')) {
    //         $query->whereDate('inspection_date', $request->inspection_date);
    //     }

    //     if ($request->filled('inspector_id')) {
    //         $query->where('inspector_id', $request->inspector_id);
    //     }

    //     if ($request->filled('business_name')) {
    //         $query->whereHas('business', function ($q) use ($request) {
    //             $q->where('business_name', 'LIKE', '%' . $request->business_name . '%');
    //         });
    //     }

    //     // Add sorting
    //     if ($request->has('sort_order')) {
    //         $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';
    //         $query->orderBy('created_at', $direction);
    //     } else {
    //         $query->orderBy('created_at', 'desc');
    //     }

    //     // Get the current page from the request, default is 1
    //     $page = $request->input('page', 1);

    //     // Paginate the results
    //     $inspections = $query->paginate(10, ['*'], 'page', $page);

    //     // Transform the inspections
    //     $inspections->getCollection()->transform(function ($inspection) {
    //         return [
    //             'inspection_id' => $inspection->inspection_id,
    //             'inspection_date' => $inspection->inspection_date,
    //             'type_of_inspection' => $inspection->type_of_inspection,
    //             'with_violations' => true,
    //             'business_id' => $inspection->business_id,
    //             'inspector_id' => $inspection->inspector_id,
    //             'created_at' => $inspection->created_at,
    //             'updated_at' => $inspection->updated_at,
    //             'image_url' => $inspection->image_url,
    //             'inspector' => [
    //                 'inspector_id' => $inspection->inspector->inspector_id,
    //                 'email' => $inspection->inspector->email,
    //                 'first_name' => $inspection->inspector->first_name,
    //                 'last_name' => $inspection->inspector->last_name
    //             ],
    //             'business' => [
    //                 'business_id' => $inspection->business->business_id,
    //                 'business_permit' => $inspection->business->business_permit,
    //                 'business_name' => $inspection->business->business_name,
    //                 'status' => $inspection->business->status,
    //                 'owner' => [
    //                     'business_owner_id' => $inspection->business->owner->business_owner_id,
    //                     'email' => $inspection->business->owner->email,
    //                     'first_name' => $inspection->business->owner->first_name,
    //                     'last_name' => $inspection->business->owner->last_name,
    //                     'phone_number' => $inspection->business->owner->phone_number
    //                 ]
    //             ],
    //             'violations' => $inspection->business->violations
    //                 ->filter(function ($violation) {
    //                     return $violation->status === 'pending'
    //                         && $violation->due_date > now()
    //                         && $violation->due_date <= now()->addDays(3);
    //                 })
    //                 ->map(function ($violation) {
    //                     return [
    //                         'violation_id' => $violation->violation_id,
    //                         'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'),
    //                         'violation_receipt_no' => $violation->violation_receipt_no,
    //                         'violation_date' => $violation->violation_date,
    //                         'due_date' => $violation->due_date,
    //                         'status' => $violation->status
    //                     ];
    //                 })
    //         ];
    //     });

    //     return response()->json([
    //         'status' => 200,
    //         'inspections' => $inspections
    //     ]);
    // }

    // public function getOverDueViolators(Request $request)
    // {
    //     // Base query to fetch inspections with overdue violations
    //     $query = Inspection::with([
    //         'business',
    //         'business.owner',
    //         'inspector',
    //         'business.violations.violationDetails'
    //     ])
    //         ->where('with_violations', 1) // Only get inspections with violations
    //         ->whereHas('business.violations', function ($q) {
    //             $q->where('status', 'pending')
    //                 ->where('due_date', '<', now());
    //         });

    //     if ($request->has('inspection_date')) {
    //         $query->whereDate('inspection_date', $request->inspection_date);
    //     }

    //     if ($request->has('inspector_id')) {
    //         $query->where('inspector_id', $request->inspector_id);
    //     }

    //     if ($request->has('business_name') && !empty($request->business_name)) {
    //         $query->whereHas('business', function ($q) use ($request) {
    //             $q->where('business_name', 'LIKE', '%' . $request->business_name . '%');
    //         });
    //     }

    //     if ($request->has('sort_order')) {
    //         $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';
    //         $query->orderBy('created_at', $direction);
    //     } else {
    //         $query->orderBy('created_at', 'desc');
    //     }

    //     $inspections = $query->get();

    //     $uniqueInspections = $inspections->groupBy('business_id')->map(function ($group) {
    //         return $group->first();
    //     });

    //     $transformedInspections = $uniqueInspections->map(function ($inspection) {
    //         return [
    //             'inspection_id' => $inspection->inspection_id,
    //             'inspection_date' => $inspection->inspection_date,
    //             'type_of_inspection' => $inspection->type_of_inspection,
    //             'with_violations' => true, // Since we filtered for this
    //             'business_id' => $inspection->business_id,
    //             'inspector_id' => $inspection->inspector_id,
    //             'created_at' => $inspection->created_at,
    //             'updated_at' => $inspection->updated_at,
    //             'image_url' => $inspection->image_url,
    //             'inspector' => [
    //                 'inspector_id' => $inspection->inspector->inspector_id,
    //                 'email' => $inspection->inspector->email,
    //                 'first_name' => $inspection->inspector->first_name,
    //                 'last_name' => $inspection->inspector->last_name
    //             ],
    //             'business' => [
    //                 'business_id' => $inspection->business->business_id,
    //                 'business_permit' => $inspection->business->business_permit,
    //                 'business_name' => $inspection->business->business_name,
    //                 'status' => $inspection->business->status,
    //                 'owner' => [
    //                     'business_owner_id' => $inspection->business->owner->business_owner_id,
    //                     'email' => $inspection->business->owner->email,
    //                     'first_name' => $inspection->business->owner->first_name,
    //                     'last_name' => $inspection->business->owner->last_name,
    //                     'phone_number' => $inspection->business->owner->phone_number
    //                 ]
    //             ],
    //             'violations' => $inspection->business->violations
    //                 ->filter(function ($violation) {
    //                     return $violation->status === 'pending'
    //                         && $violation->due_date < now();
    //                 })
    //                 ->map(function ($violation) {
    //                     $dueDate = \Carbon\Carbon::parse($violation->due_date);
    //                     $daysOverdue = (int) max(1, $dueDate->diffInDays(now())); // Ensure whole number

    //                     return [
    //                         'violation_id' => $violation->violation_id,
    //                         'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'),
    //                         'violation_receipt_no' => $violation->violation_receipt_no,
    //                         'violation_date' => $violation->violation_date,
    //                         'due_date' => $violation->due_date,
    //                         'days_overdue' => $daysOverdue, // Overdue days as whole number
    //                         'status' => $violation->status
    //                     ];
    //                 })
    //         ];
    //     });

    //     return response()->json([
    //         'status' => 200,
    //         'inspections' => $transformedInspections->values()
    //     ]);
    // }

    public function getFilteredInspections(Request $request)
    {
        // Base query with relationships
        $query = Inspection::with([
            'business',
            'business.owner',
            'business.address',
            'inspector',
            'violations.violationDetails',
        ]);

        // Filter violations based on filterType
        $filterType = $request->input('filterType');
        if ($filterType) {
            $query->whereHas('violations', function ($q) use ($filterType) {
                if ($filterType === 'upcoming_dues') {
                    $q->where('status', 'pending')
                        ->whereDate('due_date', '>=', now()->toDateString()) // Include today
                        ->whereDate('due_date', '<=', now()->addDays(3)->toDateString());
                } elseif ($filterType === 'overdue') {
                    $q->where('status', 'pending')
                        ->whereDate('due_date', '<', now()->toDateString()); // Before today
                } elseif ($filterType === 'resolved') {
                    $q->where('status', 'resolved');
                }
            });
        }

        // Apply business_name filter
        if ($request->filled('business_name')) {
            $query->whereHas('business', function ($q) use ($request) {
                $q->where('business_name', 'LIKE', '%' . $request->business_name . '%');
            });
        }

        // Apply receipt filter
        if ($request->filled('receipt')) {
            $query->whereHas('violations', function ($q) use ($request) {
                $q->where('violation_receipt_no', 'LIKE', '%' . $request->receipt . '%');
            });
        }

        // Sorting by created_at with default descending order
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy('created_at', $sortOrder);

        // Pagination
        $perPage = 20;
        $page = $request->input('page', 1);
        $inspections = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform the results
        $inspections->getCollection()->transform(function ($inspection) use ($request) {
            $filterType = $request->input('filterType');
            $violations = $inspection->violations;

            // Filter violations based on filterType
            if ($filterType === 'upcoming_dues') {
                $violations = $violations->filter(function ($violation) {
                    return $violation->status === 'pending' &&
                        $violation->due_date >= now()->toDateString() &&
                        $violation->due_date <= now()->addDays(3)->toDateString();
                });
            } elseif ($filterType === 'overdue') {
                $violations = $violations->filter(function ($violation) {
                    return $violation->status === 'pending' &&
                        $violation->due_date < now()->toDateString();
                });
            } elseif ($filterType === 'resolved') {
                $violations = $violations->filter(function ($violation) {
                    return $violation->status === 'resolved';
                });
            }

            // Format the response structure
            return [
                'inspection_id' => $inspection->inspection_id,
                'inspection_date' => $inspection->inspection_date,
                'type_of_inspection' => $inspection->type_of_inspection,
                'image_url' => $inspection->image_url,
                'business' => [
                    'business_id' => $inspection->business->business_id,
                    'business_name' => $inspection->business->business_name,
                    'business_permit' => $inspection->business->business_permit,
                    'status' => $inspection->business->status,
                    'address' => [
                        'street_address' => $inspection->business->address->street,
                        'city' => $inspection->business->address->city,
                        'postal_code' => $inspection->business->address->zip,
                    ],
                    'owner' => [
                        'first_name' => $inspection->business->owner->first_name,
                        'last_name' => $inspection->business->owner->last_name,
                        'email' => $inspection->business->owner->email,
                        'phone_number' => $inspection->business->owner->phone_number,
                    ],
                ],
                'violations' => $violations->map(function ($violation) {
                    return [
                        'violation_id' => $violation->violation_id,
                        'violation_receipt_no' => $violation->violation_receipt_no,
                        'violation_date' => $violation->violation_date,
                        'due_date' => $violation->due_date,
                        'status' => $violation->status,
                        'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'),
                    ];
                }),
                'inspector' => [
                    'inspector_id' => $inspection->inspector->inspector_id,
                    'first_name' => $inspection->inspector->first_name,
                    'last_name' => $inspection->inspector->last_name,
                    'email' => $inspection->inspector->email,
                ],
            ];
        });

        // Return the response
        return response()->json([
            'status' => 200,
            'inspections' => $inspections,
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
        $violation->violationDetails()->delete();

        // Update violation status to resolved
        $violation->status = 'resolved';
        $violation->save();

        return response()->json([
            'status' => 200,
            'message' => 'Violation resolved successfully',
            'violation' => $violation
        ]);
    }
}
