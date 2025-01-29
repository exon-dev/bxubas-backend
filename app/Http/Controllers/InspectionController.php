<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inspector;
use App\Models\Inspection;
use App\Models\Violation;
use App\Models\Notification;

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

        // if with violations
        // retrieve the sent notice, if violation_id not in notifications then
        // status = "notice not sent" else notice sent
        // then add to collections

        // Transform the inspections for consistent structure
        $inspections->getCollection()->transform(function ($inspection) {
            // Check notification status only when there are violations
            $notificationStatus = false;
            if ($inspection->with_violations && $inspection->business->violations->first()) {
                $notificationStatus = Notification::where('violation_id', $inspection->business->violations->first()->violation_id)->exists();
            }
            return [
                'inspection_id' => $inspection->inspection_id,
                'inspection_date' => $inspection->inspection_date,
                'type_of_inspection' => $inspection->type_of_inspection,
                'with_violations' => $inspection->with_violations,
                'business_id' => $inspection->business_id,
                'inspector_id' => $inspection->inspector_id,
                'created_at' => $inspection->created_at,
                'updated_at' => $inspection->updated_at,
                'notice_status' => $inspection->with_violations && $inspection->business->violations->isNotEmpty() ? ($notificationStatus ? 'notice sent' : 'notice not sent') : null,
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
                    // Check notifications for this violation
                    $notifications = \App\Models\Notification::where('violation_id', $violation->violation_id)
                        ->orderBy('created_at', 'desc')
                        ->get();

                    // Get initial notice status
                    $initialNotice = $notifications->where('title', 'Violation Notice')->first();
                    $initialNoticeStatus = $initialNotice ?
                        ($initialNotice->status === 'sent' ?
                            'Initial notice sent successfully' :
                            'Initial SMS failed: ' . ($initialNotice->error_message ?? 'Unknown error')) :
                        'No initial notice sent';

                    // Get reminder status
                    $reminder = $notifications->where('title', 'Upcoming Due Date Reminder')->first();
                    $reminderStatus = $reminder ?
                        ($reminder->status === 'sent' ?
                            'Reminder sent successfully' :
                            'Reminder SMS failed: ' . ($reminder->error_message ?? 'Unknown error')) :
                        'No reminder sent yet';

                    return [
                        'violation_id' => $violation->violation_id,
                        'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'),
                        'violation_receipt_no' => $violation->violation_receipt_no,
                        'violation_date' => $violation->violation_date,
                        'due_date' => $violation->due_date,
                        'status' => $violation->status,
                        'notifications' => [
                            'initial_notice' => [
                                'status' => $initialNotice ? $initialNotice->status : null,
                                'message' => $initialNoticeStatus
                            ],
                            'reminder' => [
                                'status' => $reminder ? $reminder->status : null,
                                'message' => $reminderStatus
                            ]
                        ]
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
                // Check notifications for this violation
                $notifications = \App\Models\Notification::where('violation_id', $violation->violation_id)
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Get initial notice status
                $initialNotice = $notifications->where('title', 'Violation Notice')->first();
                $initialNoticeStatus = $initialNotice ?
                    ($initialNotice->status === 'sent' ?
                        'Initial notice sent successfully' :
                        'Initial SMS failed: ' . ($initialNotice->error_message ?? 'Unknown error')) :
                    'No initial notice sent';

                // Get reminder status
                $reminder = $notifications->where('title', 'Upcoming Due Date Reminder')->first();
                $reminderStatus = $reminder ?
                    ($reminder->status === 'sent' ?
                        'Reminder sent successfully' :
                        'Reminder SMS failed: ' . ($reminder->error_message ?? 'Unknown error')) :
                    'No reminder sent yet';

                return [
                    'violation_id' => $violation->violation_id,
                    'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation')->toArray(),
                    'violation_receipt_no' => $violation->violation_receipt_no,
                    'violation_date' => $violation->violation_date,
                    'due_date' => $violation->due_date,
                    'status' => $violation->status,
                    'notifications' => [
                        'initial_notice' => [
                            'status' => $initialNotice ? $initialNotice->status : null,
                            'message' => $initialNoticeStatus
                        ],
                        'reminder' => [
                            'status' => $reminder ? $reminder->status : null,
                            'message' => $reminderStatus
                        ]
                    ]
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

        // Apply filterType logic for violations
        $filterType = $request->input('filterType');
        if ($filterType) {
            if ($filterType === 'overdue') {
                // For overdue, sort by due_date ascending (oldest due dates first)
                $query->whereHas('violations', function ($q) {
                    $q->where('status', 'pending')
                        ->whereDate('due_date', '<', now()->toDateString());
                })
                    ->orderBy(
                        Violation::select('due_date')
                            ->whereColumn('violations.inspection_id', 'inspections.inspection_id')
                            ->orderBy('due_date', 'asc')
                            ->limit(1)
                    );
            } else if ($filterType === 'upcoming_dues') {
                // For upcoming dues, include today and next 3 days
                $today = now()->startOfDay();
                $query->whereHas('violations', function ($q) use ($today) {
                    $q->where('status', 'pending')
                        ->whereDate('due_date', '>=', $today)
                        ->whereDate('due_date', '<=', $today->copy()->addDays(3));
                })
                    ->orderBy(
                        Violation::select('due_date')
                            ->whereColumn('violations.inspection_id', 'inspections.inspection_id')
                            ->where('status', 'pending')
                            ->whereDate('due_date', '>=', $today)
                            ->whereDate('due_date', '<=', $today->copy()->addDays(3))
                            ->orderBy('due_date', 'asc')
                            ->limit(1)
                    );
            } else if ($filterType === 'resolved') {
                $query->whereHas('violations', function ($q) {
                    $q->where('status', 'resolved');
                });
            }
        } else {
            // Default: Only include inspections that have violations
            $query->whereHas('violations');
            // Default sorting by created_at
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy('created_at', $sortOrder);
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

        // Pagination
        $perPage = 20;
        $page = $request->input('page', 1);
        $inspections = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform the results
        $inspections->getCollection()->transform(function ($inspection) use ($filterType) {
            $violations = $inspection->violations->map(function ($violation) {
                $dueDate = \Carbon\Carbon::parse($violation->due_date);
                $now = now()->startOfDay(); // Use start of day for consistent comparison

                // Calculate days until due for upcoming dues
                $daysUntilDue = $violation->status === 'pending' && $violation->due_date
                    ? $now->diffInDays($dueDate, false)
                    : null;

                // Calculate overdue days for overdue violations
                $overdueDays = $violation->status === 'pending' && $violation->due_date && $dueDate < $now
                    ? $now->diffInDays($dueDate, false)
                    : null;

                // Check notifications for this violation
                $notifications = \App\Models\Notification::where('violation_id', $violation->violation_id)
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Get initial notice status
                $initialNotice = $notifications->where('title', 'Violation Notice')->first();
                $initialNoticeStatus = $initialNotice ?
                    ($initialNotice->status === 'sent' ?
                        'Initial notice sent successfully' :
                        'Initial SMS failed: ' . ($initialNotice->error_message ?? 'Unknown error')) :
                    'No initial notice sent';

                // Get reminder status
                $reminder = $notifications->where('title', 'Upcoming Due Date Reminder')->first();
                $reminderStatus = $reminder ?
                    ($reminder->status === 'sent' ?
                        'Reminder sent successfully' :
                        'Reminder SMS failed: ' . ($reminder->error_message ?? 'Unknown error')) :
                    'No reminder sent yet';

                return [
                    'violation_id' => $violation->violation_id,
                    'violation_receipt_no' => $violation->violation_receipt_no,
                    'violation_date' => $violation->violation_date,
                    'due_date' => $violation->due_date,
                    'status' => $violation->status,
                    'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'),
                    'days_until_due' => $daysUntilDue >= 0 ? $daysUntilDue : null,
                    'days_overdue' => $overdueDays < 0 ? abs($overdueDays) : null,
                    'notifications' => [
                        'initial_notice' => [
                            'status' => $initialNotice ? $initialNotice->status : null,
                            'message' => $initialNoticeStatus
                        ],
                        'reminder' => [
                            'status' => $reminder ? $reminder->status : null,
                            'message' => $reminderStatus
                        ]
                    ]
                ];
            });

            // Sort violations based on filter type
            if ($filterType === 'overdue') {
                $violations = $violations->sortByDesc('days_overdue')->values();
            } else if ($filterType === 'upcoming_dues') {
                $violations = $violations
                    ->filter(function ($violation) {
                        return $violation['days_until_due'] !== null && $violation['days_until_due'] <= 3;
                    })
                    ->sortBy('days_until_due')
                    ->values();
            }

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
                'violations' => $violations,
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

    public function getFilteredInspectionsByInspector(Request $request)
    {
        // Base query with relationships
        $query = Inspection::with([
            'business',
            'business.owner',
            'business.address',
            'inspector',
            'violations.violationDetails',
        ]);

        // Apply inspector_id filter
        if ($request->filled('inspector_id')) {
            $query->where('inspector_id', $request->inspector_id);
        }

        // Apply filterType logic for violations
        $filterType = $request->input('filterType');
        if ($filterType) {
            if ($filterType === 'overdue') {
                // For overdue, sort by due_date ascending (oldest due dates first)
                $query->whereHas('violations', function ($q) {
                    $q->where('status', 'pending')
                        ->whereDate('due_date', '<', now()->toDateString());
                })
                    ->orderBy(
                        Violation::select('due_date')
                            ->whereColumn('violations.inspection_id', 'inspections.inspection_id')
                            ->orderBy('due_date', 'asc')
                            ->limit(1)
                    );
            } else if ($filterType === 'upcoming_dues') {
                // For upcoming dues, include today and next 3 days
                $today = now()->startOfDay();
                $query->whereHas('violations', function ($q) use ($today) {
                    $q->where('status', 'pending')
                        ->whereDate('due_date', '>=', $today)
                        ->whereDate('due_date', '<=', $today->copy()->addDays(3));
                })
                    ->orderBy(
                        Violation::select('due_date')
                            ->whereColumn('violations.inspection_id', 'inspections.inspection_id')
                            ->where('status', 'pending')
                            ->whereDate('due_date', '>=', $today)
                            ->whereDate('due_date', '<=', $today->copy()->addDays(3))
                            ->orderBy('due_date', 'asc')
                            ->limit(1)
                    );
            } else if ($filterType === 'resolved') {
                $query->whereHas('violations', function ($q) {
                    $q->where('status', 'resolved');
                });
            }
        } else {
            // Default: Only include inspections that have violations
            $query->whereHas('violations');
            // Default sorting by created_at
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy('created_at', $sortOrder);
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

        // Pagination
        $perPage = 20;
        $page = $request->input('page', 1);
        $inspections = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform the results
        $inspections->getCollection()->transform(function ($inspection) use ($filterType) {
            $violations = $inspection->violations->map(function ($violation) {
                $dueDate = \Carbon\Carbon::parse($violation->due_date);
                $now = now()->startOfDay(); // Use start of day for consistent comparison

                // Calculate days until due for upcoming dues
                $daysUntilDue = $violation->status === 'pending' && $violation->due_date
                    ? $now->diffInDays($dueDate, false)
                    : null;

                // Calculate overdue days for overdue violations
                $overdueDays = $violation->status === 'pending' && $violation->due_date && $dueDate < $now
                    ? $now->diffInDays($dueDate, false)
                    : null;

                // Check notifications for this violation
                $notifications = \App\Models\Notification::where('violation_id', $violation->violation_id)
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Get initial notice status
                $initialNotice = $notifications->where('title', 'Violation Notice')->first();
                $initialNoticeStatus = $initialNotice ?
                    ($initialNotice->status === 'sent' ?
                        'Initial notice sent successfully' :
                        'Initial SMS failed: ' . ($initialNotice->error_message ?? 'Unknown error')) :
                    'No initial notice sent';

                // Get reminder status
                $reminder = $notifications->where('title', 'Upcoming Due Date Reminder')->first();
                $reminderStatus = $reminder ?
                    ($reminder->status === 'sent' ?
                        'Reminder sent successfully' :
                        'Reminder SMS failed: ' . ($reminder->error_message ?? 'Unknown error')) :
                    'No reminder sent yet';

                return [
                    'violation_id' => $violation->violation_id,
                    'violation_receipt_no' => $violation->violation_receipt_no,
                    'violation_date' => $violation->violation_date,
                    'due_date' => $violation->due_date,
                    'status' => $violation->status,
                    'nature_of_violation' => $violation->violationDetails->pluck('nature_of_violation'),
                    'days_until_due' => $daysUntilDue >= 0 ? $daysUntilDue : null,
                    'days_overdue' => $overdueDays < 0 ? abs($overdueDays) : null,
                    'notifications' => [
                        'initial_notice' => [
                            'status' => $initialNotice ? $initialNotice->status : null,
                            'message' => $initialNoticeStatus
                        ],
                        'reminder' => [
                            'status' => $reminder ? $reminder->status : null,
                            'message' => $reminderStatus
                        ]
                    ]
                ];
            });

            // Sort violations based on filter type
            if ($filterType === 'overdue') {
                $violations = $violations->sortByDesc('days_overdue')->values();
            } else if ($filterType === 'upcoming_dues') {
                $violations = $violations
                    ->filter(function ($violation) {
                        return $violation['days_until_due'] !== null && $violation['days_until_due'] <= 3;
                    })
                    ->sortBy('days_until_due')
                    ->values();
            }

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
                'violations' => $violations,
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
}
