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
use Illuminate\Support\Facades\Log;

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
        // Validation rules
        $validationRules = [
            // Business Owner
            'owner_first_name' => 'required',
            'owner_last_name' => 'required',
            'owner_email' => 'required|email',
            'owner_phone_number' => 'required',

            // Business
            'business_name' => 'required',
            'business_permit' => 'nullable',
            'business_status' => 'required',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Adjust for file validation

            // Address
            'street' => 'required',
            'city' => 'required',
            'zip' => 'required',

            // Inspection
            'type_of_inspection' => 'required',
            'with_violations' => 'required|boolean',

            // Violations
            'nature_of_violation' => 'required_if:with_violations,true',
            'violation_receipt' => 'required_if:with_violations,true',
        ];

        if ($request->input('with_violations')) {
            $validationRules['due_date'] = 'required|date';
        }

        $data = $request->validate($validationRules);

        // Step 1: Handle file upload (if applicable)
        $fileController = new FileController();
        $imagePath = null;

        if ($request->hasFile('image')) {
            $imageResponse = $fileController->storeImage($request);
            if ($imageResponse->getStatusCode() === 200) {
                $imagePath = $imageResponse->getData()->path; // Extract path from response
            } else {
                return response()->json(['error' => 'Image upload failed'], 400);
            }
        }

        // Step 2: Add inspector_id
        $data['inspector_id'] = auth()->user()->inspector_id;

        // Step 3: Retrieve or create the business owner
        $businessOwner = BusinessOwner::firstOrCreate(
            ['email' => $data['owner_email']],
            [
                'first_name' => $data['owner_first_name'],
                'last_name' => $data['owner_last_name'],
                'phone_number' => $data['owner_phone_number'],
            ]
        );

        // Step 4: Retrieve or create the business
        $business = Business::firstOrCreate(
            ['business_permit' => $data['business_permit']],
            [
                'business_name' => $data['business_name'],
                'status' => $data['business_status'],
                'image_url' => $imagePath, // Save uploaded image URL
                'owner_id' => $businessOwner->business_owner_id,
            ]
        );

        // Step 5: Create the address
        $address = Address::create([
            'street' => $data['street'],
            'city' => $data['city'],
            'zip' => $data['zip'],
            'business_id' => $business->business_id,
        ]);

        // Step 6: Create the inspection
        $inspection = Inspection::create([
            'inspector_id' => $data['inspector_id'],
            'business_id' => $business->business_id,
            'type_of_inspection' => $data['type_of_inspection'],
            'with_violations' => $data['with_violations'],
            'inspection_date' => now(),
        ]);

        // Step 7: Create violations (if applicable)
        if ($data['with_violations']) {
            Violation::create([
                'nature_of_violation' => $data['nature_of_violation'],
                'violation_receipt_no' => $data['violation_receipt'],
                'due_date' => $data['due_date'],
                'inspection_id' => $inspection->inspection_id,
                'status' => 'pending',
                'type_of_inspection' => $data['type_of_inspection'],
                'violation_date' => now(),
                'business_id' => $business->business_id,
            ]);
        }

        // Step 8: Return response
        return response()->json([
            'message' => 'Inspection added successfully!',
            'inspection' => $inspection,
            'business' => $business,
            'owner' => $businessOwner,
            'address' => $address,
        ], 201);
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
