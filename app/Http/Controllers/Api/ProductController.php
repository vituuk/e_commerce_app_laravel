<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\imageCloud\imageCloud;

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
        // Normalize single image file or URL string to array
        if ($request->files->has('images') && !is_array($request->files->get('images'))) {
            $request->files->set('images', [$request->files->get('images')]);
        } elseif ($request->has('images') && !is_array($request->input('images'))) {
            $request->merge([
                'images' => [$request->input('images')]
            ]);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'images' => 'required|array',
            'images.*' => [
                'required',
                function ($attribute, $value, $fail) {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        $validator = \Illuminate\Support\Facades\Validator::make(
                            ['file' => $value],
                            ['file' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240']
                        );
                        if ($validator->fails()) {
                            $fail("The $attribute must be a valid image file (jpeg, png, jpg, gif, webp) under 10MB.");
                        }
                    } elseif (is_string($value)) {
                        if (!filter_var($value, FILTER_VALIDATE_URL) && !str_starts_with($value, 'http')) {
                            $fail("The $attribute must be a valid URL string.");
                        }
                    } else {
                        $fail("The $attribute must be either an image file or a valid URL string.");
                    }
                }
            ],
        ]);

        $uploadedImages = $this->processProductImages($request->images);

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

        // Normalize single image file or URL string to array
        if ($request->files->has('images') && !is_array($request->files->get('images'))) {
            $request->files->set('images', [$request->files->get('images')]);
        } elseif ($request->has('images') && !is_array($request->input('images'))) {
            $request->merge([
                'images' => [$request->input('images')]
            ]);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'description' => 'sometimes|required|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'images' => 'sometimes|required|array',
            'images.*' => [
                'required',
                function ($attribute, $value, $fail) {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        $validator = \Illuminate\Support\Facades\Validator::make(
                            ['file' => $value],
                            ['file' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240']
                        );
                        if ($validator->fails()) {
                            $fail("The $attribute must be a valid image file (jpeg, png, jpg, gif, webp) under 10MB.");
                        }
                    } elseif (is_string($value)) {
                        if (!filter_var($value, FILTER_VALIDATE_URL) && !str_starts_with($value, 'http')) {
                            $fail("The $attribute must be a valid URL string.");
                        }
                    } else {
                        $fail("The $attribute must be either an image file or a valid URL string.");
                    }
                }
            ],
        ]);

        $data = $request->only(['title', 'price', 'description', 'category_id', 'images']);
        if (isset($data['title'])) {
            $data['slug'] = $this->generateUniqueSlug($data['title'], $id);
        }
        if (isset($data['images'])) {
            $data['images'] = $this->processProductImages($data['images']);
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
                $productData['images'] = $this->processProductImages($productData['images']);
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
     * Process images: Upload files to Cloudinary, download external URLs and upload them,
     * or keep existing Cloudinary URLs.
     */
    private function processProductImages(array $images): array
    {
        $uploadedImages = [];
        $uploadDriver = env('UPLOAD_DRIVER', env('CLOUDINARY_API_KEY') ? 'cloudinary' : 'local');

        foreach ($images as $image) {
            if ($image instanceof \Illuminate\Http\UploadedFile) {
                try {
                    if ($uploadDriver === 'cloudinary') {
                        $url = imageCloud::upload($image);
                        if (!$url) {
                            throw new \RuntimeException('Cloudinary upload returned null.');
                        }
                        $uploadedImages[] = $url;
                    } else {
                        // Local storage
                        $path = \Illuminate\Support\Facades\Storage::disk('public')->putFile('products', $image);
                        $uploadedImages[] = asset('storage/' . $path);
                    }
                } catch (\Exception $e) {
                    Log::error('File upload failed: ' . $e->getMessage(), [
                        'exception' => $e
                    ]);
                    throw new \RuntimeException('Failed to upload product image: ' . $e->getMessage());
                }
            } elseif (is_string($image)) {
                // If the image is already a Cloudinary or local storage URL, keep it
                if (str_contains($image, 'res.cloudinary.com') || 
                    str_contains($image, '/storage/') || 
                    str_contains($image, '/uploads/')) {
                    $uploadedImages[] = $image;
                    continue;
                }

                // If it is a valid external URL, download and upload/store it
                if (filter_var($image, FILTER_VALIDATE_URL)) {
                    try {
                        if ($uploadDriver === 'cloudinary') {
                            $url = imageCloud::uploadFromUrl($image);
                            if ($url) {
                                $uploadedImages[] = $url;
                                continue;
                            }
                        } else {
                            $response = Http::get($image);
                            if ($response->successful()) {
                                // Local storage for external URL
                                $extension = pathinfo(parse_url($image, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                                $filename = Str::random(40) . '.' . $extension;
                                $path = 'products/' . $filename;
                                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $response->body());
                                $uploadedImages[] = asset('storage/' . $path);
                                continue;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Automatic external URL upload failed: ' . $e->getMessage(), [
                            'image_url' => $image,
                            'exception' => $e
                        ]);
                    }
                }
                $uploadedImages[] = $image;
            }
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
