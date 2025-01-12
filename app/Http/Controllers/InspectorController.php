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
        // If there are no violations, we don't need to validate due_date
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
            'image_url' => 'nullable',

            // Address
            'street' => 'required',
            'city' => 'required',
            'zip' => 'required',

            // Inspection
            'type_of_inspection' => 'required',
            'with_violations' => 'required|boolean',

            // Violations (conditionally required if with_violations is true)
            'nature_of_violation' => 'required_if:with_violations,true',
            'violation_receipt' => 'required_if:with_violations,true',
        ];

        // Conditionally add due_date validation if there are violations
        if ($request->input('with_violations') == true) {
            $validationRules['due_date'] = 'required|date';
        }

        // Validate the request data
        $data = $request->validate($validationRules);

        // Add inspector_id from the authenticated user
        $data['inspector_id'] = auth()->user()->inspector_id;

        // Step 1: Retrieve or create the business owner
        $businessOwner = BusinessOwner::firstOrCreate(
            ['email' => $data['owner_email']],
            [
                'first_name' => $data['owner_first_name'],
                'last_name' => $data['owner_last_name'],
                'phone_number' => $data['owner_phone_number'],
            ]
        );

        // Step 2: Retrieve or create the business
        $business = Business::firstOrCreate(
            ['business_permit' => $data['business_permit']],
            [
                'business_name' => $data['business_name'],
                'status' => $data['business_status'],
                'image_url' => $data['image_url'],
                'owner_id' => $businessOwner->business_owner_id,
            ]
        );

        // Step 3: Create the address and associate it with the business
        $address = Address::create([
            'street' => $data['street'],
            'city' => $data['city'],
            'zip' => $data['zip'],
            'business_id' => $business->business_id, // Set business_id in the address
        ]);

        // Step 4: Create the inspection and include inspection_date
        $inspection = Inspection::create([
            'inspector_id' => $data['inspector_id'],
            'business_id' => $business->business_id,
            'type_of_inspection' => $data['type_of_inspection'], // Ensure this is passed
            'with_violations' => $data['with_violations'],
            'inspection_date' => now(), // Set current date and time as the inspection date
        ]);

        // Step 5: Create violations (if applicable)
        if ($data['with_violations']) {
            Violation::create([
                'nature_of_violation' => $data['nature_of_violation'],
                'violation_receipt_no' => $data['violation_receipt'], // Assuming you have this field
                'due_date' => $data['due_date'], // Only passed when applicable
                'inspection_id' => $inspection->inspection_id, // Add related inspection ID
                'status' => 'pending', // Default status, adjust as needed
                'type_of_inspection' => $data['type_of_inspection'], // Ensure this is included for the violation
                'violation_date' => now(), // Set violation_date to the current date
                'business_id' => $business->business_id, // Ensure the business_id UUID is passed here
            ]);
        }

        // Return the inspection and related data
        return response([
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
        $inspector = $request->user(); // This gives the currently authenticated user

        // Retrieve the inspector's ID
        $inspectorId = $inspector->inspector_id;

        // Fetch inspections belonging to the logged-in inspector with necessary relationships
        $query = Inspection::with(['business.owner', 'inspector', 'business.violations'])
            ->where('inspector_id', $inspectorId);

        // Apply filters as needed (e.g., type of inspection, date)
        if ($request->has('type_of_inspection')) {
            $query->where('type_of_inspection', $request->type_of_inspection);
        }

        if ($request->has('inspection_date')) {
            $query->whereDate('inspection_date', $request->inspection_date);
        }

        // Check for sort_order parameter and apply sorting based on created_at
        if ($request->has('sort_order')) {
            if ($request->sort_order === 'latest') {
                $query->orderBy('created_at', 'desc'); // Sort by creation date descending
            } elseif ($request->sort_order === 'oldest') {
                $query->orderBy('created_at', 'asc'); // Sort by creation date ascending
            }
        }

        // Execute the query and get the inspections
        $inspections = $query->paginate(15);

        // Transform the response to optimize structure
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

        // Return the structured response as JSON
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
