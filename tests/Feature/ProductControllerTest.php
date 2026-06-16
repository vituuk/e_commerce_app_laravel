<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Mockery;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin user for authentication
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        // Create a test category
        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'image' => 'https://res.cloudinary.com/test-cloud/image/upload/cat.jpg',
        ]);
    }

    protected $originalEnvUploadDriver;
    protected $originalServerUploadDriver;
    protected $hasStoredOriginal = false;

    /**
     * Helper to mock Cloudinary facade.
     */
    protected function mockCloudinaryUpload()
    {
        if (!$this->hasStoredOriginal) {
            $this->originalEnvUploadDriver = $_ENV['UPLOAD_DRIVER'] ?? null;
            $this->originalServerUploadDriver = $_SERVER['UPLOAD_DRIVER'] ?? null;
            $this->hasStoredOriginal = true;
        }

        $_ENV['UPLOAD_DRIVER'] = 'cloudinary';
        $_SERVER['UPLOAD_DRIVER'] = 'cloudinary';
        putenv('UPLOAD_DRIVER=cloudinary');

        $mockedUrl = 'https://res.cloudinary.com/test-cloud/image/upload/v12345/mocked_upload.jpg';

        // Mock for v3.x API
        $uploadApiMock = Mockery::mock();
        $uploadApiMock->shouldReceive('upload')
            ->andReturn(['secure_url' => $mockedUrl]);

        Cloudinary::shouldReceive('uploadApi')->andReturn($uploadApiMock);

        // Mock for v2.x API
        Cloudinary::shouldReceive('upload')->andReturnSelf();
        Cloudinary::shouldReceive('getSecurePath')->andReturn($mockedUrl);
    }

    protected function tearDown(): void
    {
        if ($this->hasStoredOriginal) {
            if ($this->originalEnvUploadDriver === null) {
                unset($_ENV['UPLOAD_DRIVER']);
            } else {
                $_ENV['UPLOAD_DRIVER'] = $this->originalEnvUploadDriver;
            }

            if ($this->originalServerUploadDriver === null) {
                unset($_SERVER['UPLOAD_DRIVER']);
            } else {
                $_SERVER['UPLOAD_DRIVER'] = $this->originalServerUploadDriver;
            }
            $this->hasStoredOriginal = false;
        }
        putenv('UPLOAD_DRIVER');
        parent::tearDown();
    }

    public function test_admin_can_create_product_with_uploaded_images(): void
    {
        $this->mockCloudinaryUpload();

        $image1 = UploadedFile::fake()->create('image1.jpg', 100, 'image/jpeg');
        $image2 = UploadedFile::fake()->create('image2.png', 100, 'image/png');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products', [
                'title' => 'New Product',
                'price' => 19.99,
                'description' => 'A great new product',
                'category_id' => $this->category->id,
                'images' => [$image1, $image2],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('title', 'New Product');

        // Check DB state
        $product = Product::first();
        $this->assertNotNull($product);
        $this->assertEquals('New Product', $product->title);
        $this->assertCount(2, $product->images);
        $this->assertEquals('https://res.cloudinary.com/test-cloud/image/upload/v12345/mocked_upload.jpg', $product->images[0]);
    }

    public function test_admin_can_update_product_with_mix_of_urls_and_files(): void
    {
        $this->mockCloudinaryUpload();

        // Create an initial product
        $product = Product::create([
            'title' => 'Old Product',
            'slug' => 'old-product',
            'price' => 10.00,
            'description' => 'Old description',
            'category_id' => $this->category->id,
            'images' => ['https://res.cloudinary.com/test-cloud/image/upload/v999/existing.jpg'],
        ]);

        $newImage = UploadedFile::fake()->create('updated.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/products/{$product->id}", [
                'title' => 'Updated Product',
                'images' => [
                    'https://res.cloudinary.com/test-cloud/image/upload/v999/existing.jpg',
                    $newImage
                ],
            ]);

        $response->assertStatus(200);

        // Verify product was updated correctly
        $product->refresh();
        $this->assertEquals('Updated Product', $product->title);
        $this->assertCount(2, $product->images);
        $this->assertEquals('https://res.cloudinary.com/test-cloud/image/upload/v999/existing.jpg', $product->images[0]);
        $this->assertEquals('https://res.cloudinary.com/test-cloud/image/upload/v12345/mocked_upload.jpg', $product->images[1]);
    }

    public function test_product_creation_validation_fails_with_invalid_image_type(): void
    {
        $pdfFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products', [
                'title' => 'Invalid Product',
                'price' => 19.99,
                'description' => 'A description',
                'category_id' => $this->category->id,
                'images' => [$pdfFile],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    public function test_product_creation_validation_fails_with_invalid_url(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products', [
                'title' => 'Invalid Product',
                'price' => 19.99,
                'description' => 'A description',
                'category_id' => $this->category->id,
                'images' => ['not-a-valid-url'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    public function test_admin_can_create_product_with_single_uploaded_file(): void
    {
        $this->mockCloudinaryUpload();

        $image = UploadedFile::fake()->create('single_image.jpg', 100, 'image/jpeg');

        // Note: we pass 'images' as a single UploadedFile (not an array)
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products', [
                'title' => 'Single File Product',
                'price' => 10.00,
                'description' => 'Single file description',
                'category_id' => $this->category->id,
                'images' => $image,
            ]);

        $response->assertStatus(201);
        $product = Product::first();
        $this->assertCount(1, $product->images);
    }

    public function test_admin_can_create_product_with_single_url_string(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products', [
                'title' => 'Single URL Product',
                'price' => 10.00,
                'description' => 'Single URL description',
                'category_id' => $this->category->id,
                'images' => 'https://res.cloudinary.com/test-cloud/image/upload/v999/existing.jpg',
            ]);

        $response->assertStatus(201);
        $product = Product::first();
        $this->assertCount(1, $product->images);
        $this->assertEquals('https://res.cloudinary.com/test-cloud/image/upload/v999/existing.jpg', $product->images[0]);
    }

    public function test_local_image_upload_works(): void
    {
        if (!$this->hasStoredOriginal) {
            $this->originalEnvUploadDriver = $_ENV['UPLOAD_DRIVER'] ?? null;
            $this->originalServerUploadDriver = $_SERVER['UPLOAD_DRIVER'] ?? null;
            $this->hasStoredOriginal = true;
        }

        // Set environment to local driver
        $_ENV['UPLOAD_DRIVER'] = 'local';
        $_SERVER['UPLOAD_DRIVER'] = 'local';
        putenv('UPLOAD_DRIVER=local');

        // Fake public storage disk
        \Illuminate\Support\Facades\Storage::fake('public');

        $image = UploadedFile::fake()->create('local_image.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products', [
                'title' => 'Local Upload Product',
                'price' => 15.00,
                'description' => 'Local description',
                'category_id' => $this->category->id,
                'images' => [$image],
            ]);

        $response->assertStatus(201);
        $product = Product::where('title', 'Local Upload Product')->first();
        $this->assertNotNull($product);
        $this->assertCount(1, $product->images);
        
        // The URL should point to local storage
        $url = $product->images[0];
        $this->assertStringContainsString('/storage/products/', $url);

        // Get the relative path from URL to verify file exists in fake storage
        $filename = basename($url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('products/' . $filename);
    }

    public function test_local_image_upload_controller_works(): void
    {
        if (!$this->hasStoredOriginal) {
            $this->originalEnvUploadDriver = $_ENV['UPLOAD_DRIVER'] ?? null;
            $this->originalServerUploadDriver = $_SERVER['UPLOAD_DRIVER'] ?? null;
            $this->hasStoredOriginal = true;
        }

        // Set environment to local driver
        $_ENV['UPLOAD_DRIVER'] = 'local';
        $_SERVER['UPLOAD_DRIVER'] = 'local';
        putenv('UPLOAD_DRIVER=local');

        // Fake public storage disk
        \Illuminate\Support\Facades\Storage::fake('public');

        $image = UploadedFile::fake()->create('test_upload.png', 100, 'image/png');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/upload', [
                'image' => $image,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        
        $url = $response->json('url');
        $this->assertStringContainsString('/storage/uploads/', $url);

        // Get the relative path from URL to verify file exists in fake storage
        $filename = basename($url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('uploads/' . $filename);
    }
}
