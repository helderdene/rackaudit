<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFindingCategoryRequest;
use App\Models\FindingCategory;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing finding categories.
 *
 * Finding categories help organize findings by type. Default categories
 * are seeded from DiscrepancyType values, and users can create custom categories.
 */
class FindingCategoryController extends Controller
{
    /**
     * Display a listing of all finding categories.
     *
     * Returns categories ordered with defaults first, then custom sorted alphabetically.
     * Used primarily for populating select dropdowns in the finding form.
     */
    public function index(): JsonResponse
    {
        $categories = FindingCategory::query()
            ->orderedByDefault()
            ->get()
            ->map(fn (FindingCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_default' => $category->is_default,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ]);

        return response()->json(['data' => $categories]);
    }

    /**
     * Store a newly created finding category.
     *
     * Custom categories are always created with is_default=false
     * to distinguish them from the seeded default categories.
     */
    public function store(StoreFindingCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $category = FindingCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_default' => false,
        ]);

        return response()->json([
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_default' => $category->is_default,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ],
            'message' => 'Category created successfully.',
        ], 201);
    }
}
