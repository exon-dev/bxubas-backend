<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Violation;

class ViolationController extends Controller
{

    public function getViolators()
    {
        $violators = Violation::all();

        return response()->json($violators);
    }

    public function resolve($violationId)
    {
        try {
            // Find the violation
            $violation = Violation::findOrFail($violationId);

            // Update the violation status to 'paid'
            $violation->update([
                'status' => 'resolved',
            ]);

            // Return success response
            return response()->json([
                'message' => 'Violation resolved successfully',
                'violation' => $violation
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to resolve violation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
