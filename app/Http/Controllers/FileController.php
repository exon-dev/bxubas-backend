<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FileController extends Controller
{
    public function storeImage(Request $request)
    {
        // Validate the request
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Handle the image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // Name the image (use business_id if available)
            $businessId = $request->input('business_id', 'default'); // Fallback to 'default' if no business_id
            $name = $businessId . '_' . time() . '.' . $image->getClientOriginalExtension();

            // Define the destination path
            $destinationPath = public_path('/images');

            // Move the image to the destination path
            $image->move($destinationPath, $name);

            // Save the image path to the database or perform other actions
            // Example: Image::create(['path' => '/images/' . $name]);

            return response()->json([
                'success' => 'Image uploaded successfully',
                'path' => '/images/' . $name,
            ]);
        }

        return response()->json(['error' => 'Image upload failed'], 400);
    }
}
