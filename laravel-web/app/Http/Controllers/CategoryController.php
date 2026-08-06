<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymAndGlobalParentIds();

        $categories = Category::whereIn('parent_id', $parentIds)
            ->orderBy('title')
            ->get();

        return $this->success(['categories' => $categories]);
    }
}
