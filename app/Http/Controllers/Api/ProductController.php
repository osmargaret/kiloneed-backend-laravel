<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('user')->where('status', 'active')->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if (($category = $request->query('category')) && $category !== 'All') {
            $query->where('category', $category);
        }

        if (($state = $request->query('state')) && $state !== 'All') {
            $query->where('state', $state);
        }

        if (($city = $request->query('city')) && $city !== 'All') {
            $query->where('city', $city);
        }

        if (($condition = $request->query('condition')) && $condition !== 'All') {
            $query->where('condition', $condition);
        }

        if (($listingType = $request->query('listingType')) && $listingType !== 'All') {
            $query->where('listing_type', $listingType);
        }

        $products = $query->get()->map(function ($p) {
            $p->distanceKm = 3.5;
            $p->saved = false;
            return $p;
        });

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'required|string',
            'seller_type' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'category' => 'nullable|string',
            'subcategory' => 'nullable|string',
            'image' => 'nullable|string',
            'featured' => 'nullable|boolean',
            'condition' => 'nullable|string',
            'availability' => 'nullable|string',
            'listing_type' => 'nullable|string',
            'negotiable' => 'nullable|boolean',
        ]);

        $product = Product::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'price' => $validated['price'],
            'description' => $validated['description'],
            'seller_type' => $validated['seller_type'] ?? 'Business',
            'state' => $validated['state'] ?? 'Lagos',
            'city' => $validated['city'] ?? 'Ikeja',
            'category' => $validated['category'] ?? 'Electronics',
            'subcategory' => $validated['subcategory'] ?? 'General',
            'image' => $validated['image'] ?? 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500&h=500&fit=crop',
            'featured' => $validated['featured'] ?? false,
            'condition' => $validated['condition'] ?? 'Brand New',
            'availability' => $validated['availability'] ?? 'Available',
            'listing_type' => $validated['listing_type'] ?? 'For Sale',
            'negotiable' => $validated['negotiable'] ?? true,
            'status' => 'active',
        ]);

        $product->load('user');
        $product->distanceKm = 1.2;
        $product->saved = false;

        return response()->json([
            'message' => 'Product listed on marketplace successfully',
            'product' => $product,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with('user')->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->only([
            'title',
            'price',
            'description',
            'state',
            'city',
            'category',
            'subcategory',
            'image',
            'featured',
            'condition',
            'availability',
            'listing_type',
            'negotiable',
            'status',
        ]));

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh('user'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Product removed successfully',
        ]);
    }
}
