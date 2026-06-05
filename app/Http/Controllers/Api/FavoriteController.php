<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $favorites = Favorite::with('product.category')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json($favorites);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        // Check if already favorited
        $existing = Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Product already in favorites',
                'favorite' => $existing->load('product.category')
            ], 200);
        }

        $favorite = Favorite::create([
            'user_id' => $request->user()->id,
            'product_id' => $request->product_id,
        ]);

        return response()->json($favorite->load('product.category'), 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $favorite = Favorite::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $favorite->delete();

        return response()->json(['message' => 'Removed from favorites']);
    }
}
