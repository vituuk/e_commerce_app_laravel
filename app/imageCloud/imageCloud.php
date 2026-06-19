<?php

namespace App\imageCloud;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class imageCloud
{
    /**
     * Upload an image file or path to Cloudinary.
     *
     * @param mixed $image File instance or string path.
     * @param array $options Optional upload parameters.
     * @return string|null Secure URL of uploaded image or null on failure.
     */
    public static function upload($image, array $options = []): ?string
    {
        try {
            $filePath = is_string($image) ? $image : $image->getRealPath();

            $defaultOptions = [
                'folder' => 'e-commerce-products',
                'use_filename' => true,
                'unique_filename' => true,
            ];

            $finalOptions = array_merge($defaultOptions, $options);

            if (method_exists(Cloudinary::class, 'uploadApi')) {
                $result = Cloudinary::uploadApi()->upload($filePath, $finalOptions);
                return $result['secure_url'];
            } else {
                $response = Cloudinary::upload($filePath, $finalOptions);
                return $response->getSecurePath();
            }
        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Download an external URL and upload it to Cloudinary.
     *
     * @param string $url The external image URL.
     * @param array $options Optional upload parameters.
     * @return string|null Secure URL of uploaded image or null on failure.
     */
    public static function uploadFromUrl(string $url, array $options = []): ?string
    {
        try {
            $response = Http::get($url);
            if (!$response->successful()) {
                Log::error("Failed to download image from URL: {$url}");
                return null;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'img');
            file_put_contents($tempFile, $response->body());

            $secureUrl = self::upload($tempFile, $options);

            unlink($tempFile);

            return $secureUrl;
        } catch (\Exception $e) {
            Log::error('Cloudinary upload from URL failed: ' . $e->getMessage(), [
                'url' => $url,
                'exception' => $e
            ]);
            return null;
        }
    }
}
