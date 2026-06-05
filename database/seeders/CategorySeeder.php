<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'image' => 'https://picsum.photos/seed/electronics/400/300',
            ],
            [
                'name' => 'Clothing',
                'slug' => 'clothing',
                'image' => 'https://picsum.photos/seed/clothing/400/300',
            ],
            [
                'name' => 'Furniture',
                'slug' => 'furniture',
                'image' => 'https://picsum.photos/seed/furniture/400/300',
            ],
            [
                'name' => 'Shoes',
                'slug' => 'shoes',
                'image' => 'https://picsum.photos/seed/shoes/400/300',
            ],
            [
                'name' => 'Miscellaneous',
                'slug' => 'miscellaneous',
                'image' => 'https://picsum.photos/seed/misc/400/300',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
