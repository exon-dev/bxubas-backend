<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


class FileController extends Controller
{
    public function storeImage(Request $request)
    {
        // Check if the image file is present in the request
        if ($request->hasFile('image')) {
            // Get the image file from the request
            $image = $request->file('image');

            // Define the storage path, storing the image in the 'images' directory
            $path = $image->store('images', 'public'); // Store the file in the 'public' disk

            // Return the path of the uploaded image
            return $path; // Example: 'images/myimage.jpg'
        }

        // Return null if no image is provided
        return null;
    }


}
