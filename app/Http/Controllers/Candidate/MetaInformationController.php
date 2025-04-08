<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\JobCategory;
use Illuminate\Http\JsonResponse;

/**
 * Controller for meta information
 */
class MetaInformationController extends Controller
{
    /**
     * Get language proficiency options
     *
     * @return JsonResponse
     */
    public function languageProficiency(): JsonResponse
    {
        $proficiencies = [
            ['value' => 'beginner', 'label' => 'Beginner'],
            ['value' => 'intermediate', 'label' => 'Intermediate'],
            ['value' => 'advanced', 'label' => 'Advanced'],
            ['value' => 'native', 'label' => 'Native'],
        ];

        return response()->json([
            'success' => true,
            'data' => $proficiencies,
        ]);
    }

    /**
     * Get job categories
     *
     * @return JsonResponse
     */
    public function getJobCategory(): JsonResponse
    {
        $categories = JobCategory::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get single job category
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getSingleCategory(int $id): JsonResponse
    {
        $category = JobCategory::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }
}
