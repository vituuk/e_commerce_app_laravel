<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UploadController extends Controller
{
    /**
     * Upload an image to Cloudinary and return the secure URL.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // max 10MB
        ]);

        try {
            $filePath = $request->file('image')->getRealPath();
            $options = [
                'folder' => 'e-commerce-products',
                'use_filename' => true,
                'unique_filename' => true,
            ];

            // Defensive version checking to support both v2.x and v3.x of cloudinary-laravel package
            if (method_exists(Cloudinary::class, 'uploadApi')) {
                // v3.x API
                $result = Cloudinary::uploadApi()->upload($filePath, $options);
                $url = $result['secure_url'];
                $publicId = $result['public_id'];
            } else {
                // v2.x API
                $response = Cloudinary::upload($filePath, $options);
                $url = $response->getSecurePath();
                $publicId = $response->getPublicId();
            }

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'url' => $url,
                'public_id' => $publicId,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
