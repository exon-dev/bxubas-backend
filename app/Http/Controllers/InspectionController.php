<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inspector;
use App\Models\Inspection;
use App\Models\Violation;

class InspectionController extends Controller
{
    //
    public function getInspections(Request $request)
    {
        // Get the current page from the request, default is 1
        $page = $request->input('page', 1);

        // Paginate the inspections, 10 per page
        $inspections = Inspection::with(['business', 'business.owner', 'inspector'])->paginate(20, ['*'], 'page', $page);

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


    public function getCardInfo()
    {
        $total_inspectors = Inspector::count();
        $total_inspections = Inspection::sum('inspection_id');
        $total_violators = Inspection::where('with_violations', 1)->count();
        $overdue_violation = Violation::where('due_date', '<', now())->count();

        return response()->json([
            'total_inspectors' => $total_inspectors,
            'total_inspections' => $total_inspections,
            'total_violators' => $total_violators,
            'overdue_violation' => $overdue_violation,
        ]);
    }

    // todo work on this later
    public function resolveViolation($violation_id)
    {
        $violation = Violation::find($violation_id);
        $violation->resolved = 1;
        $violation->save();

        return response()->json([
            'status' => 200,
            'message' => 'Violation resolved successfully',
            'violation' => $violation
        ]);
    }
}
