<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $electronics = Category::where('slug', 'electronics')->first();
        $clothing = Category::where('slug', 'clothing')->first();
        $furniture = Category::where('slug', 'furniture')->first();
        $shoes = Category::where('slug', 'shoes')->first();

        $products = [
            [
                'title' => 'Wireless Headphones',
                'slug' => 'wireless-headphones',
                'price' => 99.99,
                'description' => 'High-quality wireless headphones with noise cancellation',
                'category_id' => $electronics->id,
                'images' => [
                    'https://picsum.photos/seed/headphones1/600/600',
                    'https://picsum.photos/seed/headphones2/600/600',
                ],
            ],
            [
                'title' => 'Smart Watch',
                'slug' => 'smart-watch',
                'price' => 299.99,
                'description' => 'Feature-rich smartwatch with fitness tracking',
                'category_id' => $electronics->id,
                'images' => [
                    'https://picsum.photos/seed/watch1/600/600',
                    'https://picsum.photos/seed/watch2/600/600',
                ],
            ],
            [
                'title' => 'Cotton T-Shirt',
                'slug' => 'cotton-t-shirt',
                'price' => 29.99,
                'description' => 'Comfortable 100% cotton t-shirt',
                'category_id' => $clothing->id,
                'images' => [
                    'https://picsum.photos/seed/tshirt1/600/600',
                    'https://picsum.photos/seed/tshirt2/600/600',
                ],
            ],
            [
                'title' => 'Denim Jeans',
                'slug' => 'denim-jeans',
                'price' => 79.99,
                'description' => 'Classic fit denim jeans',
                'category_id' => $clothing->id,
                'images' => [
                    'https://picsum.photos/seed/jeans1/600/600',
                    'https://picsum.photos/seed/jeans2/600/600',
                ],
            ],
            [
                'title' => 'Modern Office Chair',
                'slug' => 'modern-office-chair',
                'price' => 249.99,
                'description' => 'Ergonomic office chair with lumbar support',
                'category_id' => $furniture->id,
                'images' => [
                    'https://picsum.photos/seed/chair1/600/600',
                    'https://picsum.photos/seed/chair2/600/600',
                ],
            ],
            [
                'title' => 'Wooden Desk',
                'slug' => 'wooden-desk',
                'price' => 399.99,
                'description' => 'Solid wood desk with storage drawers',
                'category_id' => $furniture->id,
                'images' => [
                    'https://picsum.photos/seed/desk1/600/600',
                    'https://picsum.photos/seed/desk2/600/600',
                ],
            ],
            [
                'title' => 'Running Shoes',
                'slug' => 'running-shoes',
                'price' => 129.99,
                'description' => 'Lightweight running shoes with cushioned sole',
                'category_id' => $shoes->id,
                'images' => [
                    'https://picsum.photos/seed/running1/600/600',
                    'https://picsum.photos/seed/running2/600/600',
                ],
            ],
            [
                'title' => 'Casual Sneakers',
                'slug' => 'casual-sneakers',
                'price' => 89.99,
                'description' => 'Stylish casual sneakers for everyday wear',
                'category_id' => $shoes->id,
                'images' => [
                    'https://picsum.photos/seed/sneakers1/600/600',
                    'https://picsum.photos/seed/sneakers2/600/600',
                ],
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
