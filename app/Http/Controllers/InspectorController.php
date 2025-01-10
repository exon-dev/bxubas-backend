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
            'business_permit' => 'required',
            'business_status' => 'required',
            'image_url' => 'required|url',

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
        // Start with a base query
        $query = Inspection::with(['business', 'business.owner', 'inspector']);

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

        if ($request->has('business_name')) {
            $query->whereHas('business', function ($q) use ($request) {
                $q->where('business_name', 'LIKE', '%' . $request->business_name . '%');
            });
        }

        // Get the current page from the request, default is 1
        $page = $request->input('page', 1);

        // Paginate the filtered results
        $inspections = $query->paginate(15, ['*'], 'page', $page);

        // Modify the inspections to include the inspector's full name
        $inspections->getCollection()->transform(function ($inspection) {
            $inspection->inspector_full_name = $inspection->inspector->first_name . ' ' . $inspection->inspector->last_name;
            return $inspection;
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
