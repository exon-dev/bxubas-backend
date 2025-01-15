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
        // Log the request for debugging
        Log::info('Add Inspection Request', ['request' => $request->all()]);

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
                // Create or update business owner
                $businessOwner = BusinessOwner::firstOrCreate(
                    ['email' => $data['owner_email']],
                    [
                        'first_name' => $data['owner_first_name'],
                        'last_name' => $data['owner_last_name'],
                        'phone_number' => $data['owner_phone_number'],
                    ]
                );

                // Create or update business
                $business = Business::firstOrCreate(
                    ['business_permit' => $data['business_permit']],
                    [
                        'business_name' => $data['business_name'],
                        'status' => $data['business_status'],
                        'owner_id' => $businessOwner->business_owner_id,
                    ]
                );

                // Create address
                $address = Address::create([
                    'street' => $data['street'],
                    'city' => $data['city'],
                    'zip' => $data['zip'],
                    'business_id' => $business->business_id,
                ]);

                // Create inspection
                $inspection = Inspection::create([
                    'inspector_id' => $data['inspector_id'],
                    'image_url' => $imagePath,
                    'business_id' => $business->business_id,
                    'type_of_inspection' => $data['type_of_inspection'],
                    'with_violations' => $hasViolations,
                    'inspection_date' => now(),
                ]);

                // Handle violations if present
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

                    // Create violation details
                    foreach ($data['nature_of_violations'] as $natureOfViolation) {
                        ViolationDetail::create([
                            'violation_id' => $violation->violation_id,
                            'nature_of_violation' => $natureOfViolation,
                        ]);
                    }
                }

                // Commit transaction
                DB::commit();

                return response()->json([
                    'message' => 'Inspection added successfully!',
                    'inspection' => $inspection->load(['business.owner', 'violations.violationDetails']),
                    'image_url' => $imagePath,
                ], 201);

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
            Log::error('Error adding inspection', ['error' => $e->getMessage()]);
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
