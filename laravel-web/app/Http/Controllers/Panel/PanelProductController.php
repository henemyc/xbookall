<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Product;
use Illuminate\Http\Request;

class PanelProductController extends BaseController
{
    public function index()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('products_enabled', true)) {
            return redirect()->route('panel.dashboard')->with('error', \App\Services\SubscriptionFeatureService::featureLockedMessage('Products'));
        }

        $products = Product::whereIn('parent_id', $parentIds)
            ->orderBy('title')
            ->get();

        return view('panel.products.index', compact('products'));
    }

    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('products_enabled', true)) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::featureLockedMessage('Products'));
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        $product = Product::create([
            'parent_id' => $pid,
            'title' => $request->title,
            'description' => $request->description ?? '',
            'price' => $request->price,
            'discount' => $request->discount ?? 0,
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Product added successfully',
                'product' => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'description' => $product->description,
                    'price' => $product->price,
                    'discount' => $product->discount ?? 0,
                ]
            ]);
        }

        return redirect()->route('panel.products.index')->with('success', 'Product added');
    }

    public function update(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $product = Product::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        $product->update([
            'title' => $request->title,
            'description' => $request->description ?? '',
            'price' => $request->price,
            'discount' => $request->discount ?? 0,
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'product' => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'description' => $product->description,
                    'price' => $product->price,
                    'discount' => $product->discount ?? 0,
                ]
            ]);
        }

        return redirect()->route('panel.products.index')->with('success', 'Product updated');
    }

    public function destroy(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $product = Product::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();
        $product->delete();

        $isAjax = request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json(['success' => true, 'message' => 'Product deleted']);
        }

        return redirect()->route('panel.products.index')->with('success', 'Product deleted');
    }
}
