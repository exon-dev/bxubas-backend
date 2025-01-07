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


    public function resolveViolation()
    {

    }

}
