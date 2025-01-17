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
