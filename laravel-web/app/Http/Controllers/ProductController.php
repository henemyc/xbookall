<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class ProductController extends BaseController
{
    /**
     * List products
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('products.view')) {
            return $this->error('Permission denied', 403);
        }

        if (!$this->planFeatureEnabled('products_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Products'), 402);
        }
        $parentIds = $this->getGymParentIds();

        $products = Product::whereIn('parent_id', $parentIds)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['products' => $products]);
    }

    /**
     * Create product
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('products.create')) {
            return $this->error('Permission denied', 403);
        }

        if (!Schema::hasTable('products')) {
            return $this->error('Products database table is missing. Run the database update from Super Admin Settings.', 503);
        }
        $pid = $this->getParentId();
        if ($pid <= 0) {
            return $this->error('Gym workspace could not be verified. Please login again.', 401);
        }
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('products_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Products'), 402);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:100',
        ]);

        $product = Product::create([
            'parent_id' => $pid,
            'title' => $request->title,
            'description' => $request->description ?? '',
            'price' => $request->price,
            'discount' => $request->discount,
        ]);

        return $this->success([
            'id' => $product->id,
            'product' => $product,
        ], 'Product added', 201);
    }

    /**
     * Update product
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('products.edit')) {
            return $this->error('Permission denied', 403);
        }

        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $product = Product::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$product) {
            return $this->error('Product not found', 404);
        }

        $product->update([
            'title' => $request->title ?? $product->title,
            'description' => $request->description ?? $product->description,
            'price' => $request->price ?? $product->price,
            'discount' => $request->discount ?? $product->discount,
        ]);

        return $this->success([], 'Product updated');
    }

    /**
     * Delete product
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('products.delete')) {
            return $this->error('Permission denied', 403);
        }

        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $product = Product::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$product) {
            return $this->error('Product not found', 404);
        }

        $product->delete();

        return $this->success([], 'Product deleted');
    }
}
