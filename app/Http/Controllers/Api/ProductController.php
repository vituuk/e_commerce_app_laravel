<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::with('category');

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'ILIKE', '%' . $request->search . '%');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'images' => 'required|array',
            'images.*' => 'string',
        ]);

        $uploadedImages = $this->uploadExternalImages($request->images);

        $product = Product::create([
            'title' => $request->title,
            'slug' => $this->generateUniqueSlug($request->title),
            'price' => $request->price,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'images' => $uploadedImages,
        ]);

        return response()->json($product->load('category'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with('category')->findOrFail($id);
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'description' => 'sometimes|required|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'images' => 'sometimes|required|array',
            'images.*' => 'string',
        ]);

        $data = $request->only(['title', 'price', 'description', 'category_id', 'images']);
        if (isset($data['title'])) {
            $data['slug'] = $this->generateUniqueSlug($data['title'], $id);
        }
        if (isset($data['images'])) {
            $data['images'] = $this->uploadExternalImages($data['images']);
        }

        $product->update($data);

        return response()->json($product->load('category'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Bulk insert products.
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.title' => 'required|string|max:255',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.description' => 'required|string',
            'products.*.category_id' => 'required|exists:categories,id',
            'products.*.images' => 'required|array',
            'products.*.images.*' => 'string',
            'products.*.slug' => 'sometimes|string',
        ]);

        $createdProducts = [];

        foreach ($request->products as $productData) {
            // Auto-generate slug if not provided
            if (!isset($productData['slug'])) {
                $productData['slug'] = $this->generateUniqueSlug($productData['title']);
            } else {
                $productData['slug'] = $this->generateUniqueSlug($productData['slug']);
            }

            if (isset($productData['images'])) {
                $productData['images'] = $this->uploadExternalImages($productData['images']);
            }

            $product = Product::create($productData);
            $createdProducts[] = $product->load('category');
        }

        return response()->json([
            'message' => 'Products created successfully',
            'count' => count($createdProducts),
            'products' => $createdProducts
        ], 201);
    }

    /**
     * Download external images and upload them to Cloudinary.
     */
    private function uploadExternalImages(array $images): array
    {
        $uploadedImages = [];
        foreach ($images as $imageUrl) {
            // If the image is already a Cloudinary URL, keep it
            if (str_contains($imageUrl, 'res.cloudinary.com')) {
                $uploadedImages[] = $imageUrl;
                continue;
            }

            // Check if it is a valid external HTTP/HTTPS URL
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                try {
                    $response = Http::get($imageUrl);
                    if ($response->successful()) {
                        $tempFile = tempnam(sys_get_temp_dir(), 'img');
                        file_put_contents($tempFile, $response->body());

                        $options = [
                            'folder' => 'e-commerce-products',
                            'use_filename' => true,
                            'unique_filename' => true,
                        ];

                        if (method_exists(Cloudinary::class, 'uploadApi')) {
                            $result = Cloudinary::uploadApi()->upload($tempFile, $options);
                            $url = $result['secure_url'];
                        } else {
                            $result = Cloudinary::upload($tempFile, $options);
                            $url = $result->getSecurePath();
                        }

                        unlink($tempFile);
                        $uploadedImages[] = $url;
                        continue;
                    }
                } catch (\Exception $e) {
                    Log::error('Cloudinary automatic upload failed: ' . $e->getMessage(), [
                        'image_url' => $imageUrl,
                        'exception' => $e
                    ]);
                }
            }
            $uploadedImages[] = $imageUrl;
        }
        return $uploadedImages;
    }

    /**
     * Generate a unique slug for products.
     */
    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        $query = Product::where('slug', $slug);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $count;
            $query = Product::where('slug', $slug);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
            $count++;
        }

        return $slug;
    }
}
