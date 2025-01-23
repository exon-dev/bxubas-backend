<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inspector;
use Illuminate\Support\Facades\Hash;
use App\Models\Inspection;
use App\Models\Violation;
use App\Models\BusinessOwner;
use App\Models\Business;
use App\Models\Address;
use App\Models\ViolationDetail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;

class InspectorController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $inspector = Inspector::where('email', $data['email'])->first();

        if (!$inspector) {
            return response(['message' => 'Inspector account does not exist!'], 404);
        }

        if (!Hash::check($data['password'], $inspector->password)) {
            return response([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $inspector->createToken('auth_token', ['inspector'])->plainTextToken;

        return response([
            'inspector' => $inspector,
            'token' => $token
        ]);
    }

    public function addInspection(Request $request)
    {
        Log::info('Add Inspection Request', ['request' => $request->all()]);

        // Cast `with_violations` to boolean
        $request->merge([
            'with_violations' => filter_var($request->with_violations, FILTER_VALIDATE_BOOLEAN),
        ]);

        try {
            $validationRules = [
                'owner_first_name' => 'required|string',
                'owner_last_name' => 'required|string',
                'owner_email' => 'nullable|email',
                'owner_phone_number' => 'required|string',
                'business_name' => 'required|string',
                'business_permit' => 'nullable|string',
                'business_status' => 'required|string',
                'street' => 'required|string',
                'city' => 'required|string',
                'zip' => 'required|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'type_of_inspection' => 'required|string',
                'with_violations' => 'required|boolean',
            ];

            $hasViolations = filter_var($request->input('with_violations'), FILTER_VALIDATE_BOOLEAN);

            if ($hasViolations) {
                $validationRules['nature_of_violations'] = 'required|array|min:1';
                $validationRules['nature_of_violations.*'] = 'required|string';
                $validationRules['violation_receipt'] = 'required|string';
                $validationRules['due_date'] = 'required|date';
            }

            $data = $request->validate($validationRules);

            $fileController = new FileController();
            $imagePath = $request->hasFile('image') ? $fileController->storeImage($request) : null;

            $data['inspector_id'] = auth()->user()->inspector_id;

            DB::beginTransaction();

            try {
                // Check if email is provided
                if ($data['owner_email']) {
                    // If email is provided, use firstOrCreate
                    $businessOwner = BusinessOwner::firstOrCreate(
                        ['email' => $data['owner_email']],
                        [
                            'first_name' => $data['owner_first_name'],
                            'last_name' => $data['owner_last_name'],
                            'phone_number' => $data['owner_phone_number'],
                        ]
                    );
                } else {
                    // If no email, create a new business owner directly
                    $businessOwner = BusinessOwner::create([
                        'first_name' => $data['owner_first_name'],
                        'last_name' => $data['owner_last_name'],
                        'phone_number' => $data['owner_phone_number'],
                    ]);
                }

                $business = Business::firstOrCreate(
                    ['business_permit' => $data['business_permit']],
                    [
                        'business_name' => $data['business_name'],
                        'status' => $data['business_status'],
                        'owner_id' => $businessOwner->business_owner_id,
                    ]
                );

                // First check if an address exists for this business
                $existingAddress = Address::where('business_id', $business->business_id)->first();

                if (!$existingAddress) {
                    $address = Address::create([
                        'street' => $data['street'],
                        'city' => $data['city'],
                        'zip' => $data['zip'],
                        'business_id' => $business->business_id,
                    ]);
                }

                // Check if an inspection already exists for this business on the same date
                $existingInspection = Inspection::where('business_id', $business->business_id)
                    ->whereDate('inspection_date', now()->toDateString())
                    ->first();

                if ($existingInspection) {
                    DB::rollBack();
                    return response()->json(['error' => 'Inspection already exists for this business on the same date.'], 400);
                }

                $inspection = Inspection::create([
                    'inspector_id' => $data['inspector_id'],
                    'image_url' => $imagePath,
                    'business_id' => $business->business_id,
                    'type_of_inspection' => $data['type_of_inspection'],
                    'with_violations' => $hasViolations,
                    'inspection_date' => now(),
                ]);

                if ($hasViolations) {
                    $violation = Violation::create([
                        'violation_receipt_no' => $data['violation_receipt'],
                        'due_date' => $data['due_date'],
                        'inspection_id' => $inspection->inspection_id,
                        'status' => 'pending',
                        'type_of_inspection' => $data['type_of_inspection'],
                        'violation_date' => now(),
                        'business_id' => $business->business_id,
                    ]);

                    foreach ($data['nature_of_violations'] as $natureOfViolation) {
                        ViolationDetail::create([
                            'violation_id' => $violation->violation_id,
                            'nature_of_violation' => $natureOfViolation,
                        ]);
                    }

                    Notification::create([
                        'title' => 'Violation Notice',
                        'content' => "Your business '{$business->business_name}' has received a violation notice. Receipt #: {$violation->violation_receipt_no}, Due Date: {$violation->due_date}.",
                        'violator_id' => $businessOwner->business_owner_id,
                        'violation_id' => $violation->violation_id,
                    ]);

                    $this->sendViolationNotification($businessOwner, $business, $violation);
                }

                DB::commit();

                return response()->json([
                    'message' => 'Inspection added successfully!',
                    'inspection' => $inspection->load(['business.owner', 'violations.violationDetails']),
                    'image_url' => $imagePath,
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error adding inspection', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Internal Server Error', 'debug' => $e->getMessage()], 500);
        }
    }

    private function sendViolationNotification($businessOwner, $business, $violation)
    {
        try {
            $phoneNumber = $businessOwner->phone_number;

            Log::info('Formatted phone number', ['phone' => $phoneNumber]);

            // Format the due date
            $dueDate = date('F j, Y', strtotime($violation->due_date));

            // Send the notification with the formatted phone number
            // Send a friendly notification about potential violations
            $notificationController = new NotificationController();
            $notificationController->sendNotification(new Request([
                'phone' => $phoneNumber,
                'message' => "Hi {$businessOwner->first_name} {$businessOwner->last_name},

You have a new violation notice from the Business Permit and Licensing Department (BPLD). Please visit our office on or before {$dueDate}, and refer to the reciept {$violation->violation_receipt_no}.

Thank you!"
            ]));


            // Log the notification attempt
            Log::info('Using NotificationController to send SMS', [
                'business_owner' => $businessOwner->business_owner_id,
                'phone' => $phoneNumber,
                'business' => $business->business_name,
                'violation' => $violation->violation_receipt_no
            ]);

            Log::info('Violation notification sent successfully', ['business_owner' => $businessOwner->email]);
        } catch (\Exception $e) {
            Log::error('Failed to send violation notification', ['error' => $e->getMessage()]);
        }
    }



    public function updateInspection(Request $request, $id)
    {
        // Log the request for debugging
        Log::info('Update Inspection Request', ['request' => $request->all(), 'id' => $id]);

        try {
            // Base validation rules (always required)
            $validationRules = [
                // Business Owner
                'owner_first_name' => 'required|string',
                'owner_last_name' => 'required|string',
                'owner_email' => 'nullable|email',
                'owner_phone_number' => 'required|string',

                // Business
                'business_name' => 'required|string',
                'business_permit' => 'nullable|string',
                'business_status' => 'required|string',

                // Address
                'street' => 'required|string',
                'city' => 'required|string',
                'zip' => 'required|string',

                // Inspection
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'type_of_inspection' => 'required|string',
                'with_violations' => 'required|in:0,1,true,false',
            ];

            // Convert with_violations to boolean
            $hasViolations = filter_var($request->input('with_violations'), FILTER_VALIDATE_BOOLEAN);

            // Add conditional validation rules only if with_violations is true
            if ($hasViolations) {
                $validationRules['nature_of_violations'] = 'required|array';
                $validationRules['nature_of_violations.*'] = 'required|string';
                $validationRules['violation_receipt'] = 'required|string';
                $validationRules['due_date'] = 'required|date';
            }

            // Validate the request
            $data = $request->validate($validationRules);

            // Handle file upload
            $fileController = new FileController();
            $imagePath = null;

            if ($request->hasFile('image')) {
                $imagePath = $fileController->storeImage($request);

                if (!$imagePath) {
                    throw new \Exception('Image upload failed');
                }

                Log::info('Image uploaded successfully', ['path' => $imagePath]);
            }

            // Add inspector_id to data
            $data['inspector_id'] = auth()->user()->inspector_id;

            // Begin database transaction
            DB::beginTransaction();

            try {
                // Find existing inspection
                $inspection = Inspection::findOrFail($id);

                // Update business owner
                $businessOwner = BusinessOwner::updateOrCreate(
                    ['email' => $data['owner_email']],
                    [
                        'first_name' => $data['owner_first_name'],
                        'last_name' => $data['owner_last_name'],
                        'phone_number' => $data['owner_phone_number'],
                    ]
                );

                // Update business
                $business = Business::updateOrCreate(
                    ['business_permit' => $data['business_permit']],
                    [
                        'business_name' => $data['business_name'],
                        'status' => $data['business_status'],
                        'owner_id' => $businessOwner->business_owner_id,
                    ]
                );

                // Update address
                $address = Address::updateOrCreate(
                    ['business_id' => $business->business_id],
                    [
                        'street' => $data['street'],
                        'city' => $data['city'],
                        'zip' => $data['zip'],
                    ]
                );

                // Update inspection
                $inspection->update([
                    'inspector_id' => $data['inspector_id'],
                    'image_url' => $imagePath,
                    'business_id' => $business->business_id,
                    'type_of_inspection' => $data['type_of_inspection'],
                    'with_violations' => $hasViolations,
                    'inspection_date' => now(),
                ]);

                if ($hasViolations) {
                    // Handle violations if present
                    $violation = Violation::updateOrCreate(
                        ['inspection_id' => $inspection->inspection_id],
                        [
                            'violation_receipt_no' => $data['violation_receipt'],
                            'due_date' => $data['due_date'],
                            'status' => 'pending',
                            'type_of_inspection' => $data['type_of_inspection'],
                            'violation_date' => now(),
                            'business_id' => $business->business_id,
                        ]
                    );

                    // Delete existing violation details
                    ViolationDetail::where('violation_id', $violation->violation_id)->delete();

                    // Create new violation details
                    foreach ($data['nature_of_violations'] as $natureOfViolation) {
                        ViolationDetail::create([
                            'violation_id' => $violation->violation_id,
                            'nature_of_violation' => $natureOfViolation,
                        ]);
                    }
                } else {
                    // If `with_violations` is false, delete associated violations and details
                    $violation = Violation::where('inspection_id', $inspection->inspection_id)->first();

                    if ($violation) {
                        // Delete violation details
                        ViolationDetail::where('violation_id', $violation->violation_id)->delete();

                        // Delete the violation itself
                        $violation->delete();
                    }
                }

                // Commit transaction
                DB::commit();

                return response()->json([
                    'message' => 'Inspection updated successfully!',
                    'inspection' => $inspection->load(['business.owner', 'violations.violationDetails']),
                    'image_url' => $imagePath,
                ], 200);

            } catch (\Exception $e) {
                // Rollback transaction on error
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating inspection', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => 'An error occurred while processing your request.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function getInspections(Request $request)
    {
        // Use the inspector guard to fetch the logged-in inspector's details
        $inspector = $request->user();
        $inspectorId = $inspector->inspector_id;

        // Start with the base query
        $query = Inspection::with(['business.owner', 'inspector', 'business.violations'])
            ->where('inspector_id', $inspectorId);

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

        // Apply existing filters
        if ($request->has('type_of_inspection')) {
            $query->where('type_of_inspection', $request->type_of_inspection);
        }

        if ($request->has('inspection_date')) {
            $query->whereDate('inspection_date', $request->inspection_date);
        }

        // Handle violations filter if present
        if ($request->has('with_violations')) {
            $withViolations = $request->with_violations === 'yes';
            $query->where('with_violations', $withViolations);
        }

        // Updated sort order handling
        if ($request->has('sort_order')) {
            $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';
            $query->orderBy('created_at', $direction);
        } else {
            // Default sorting if not specified
            $query->orderBy('created_at', 'desc');
        }

        // Execute the query and get the inspections
        $inspections = $query->paginate(15);

        // Transform the response (keeping your existing transformation logic)
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

    public function deleteInspection($id)
    {
        $inspection = Inspection::find($id);
        $inspection->delete();
        return response(['message' => 'Inspection deleted']);
    }
}


