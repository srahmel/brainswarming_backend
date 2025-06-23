<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="UpdateEntryRequest",
 *     title="Update Entry Request",
 *     description="Request for updating an existing entry",
 *     @OA\Property(property="problem", type="string", example="Updated customer response time issue", description="Description of the problem"),
 *     @OA\Property(property="solution", type="string", example="Implement AI-based response system", description="Proposed solution to the problem"),
 *     @OA\Property(property="area", type="string", example="Customer Service", description="Business area affected by the problem/solution"),
 *     @OA\Property(property="time_saved_per_year", type="integer", nullable=true, example=600, description="Estimated time saved per year in hours"),
 *     @OA\Property(property="gross_profit_per_year", type="integer", nullable=true, example=12000, description="Estimated gross profit increase per year"),
 *     @OA\Property(property="effort", type="string", enum={"low", "medium", "high"}, example="low", description="Estimated effort to implement the solution"),
 *     @OA\Property(property="monetary_explanation", type="string", example="Saves 600 hours per year at $20/hour", description="Explanation of the monetary benefits"),
 *     @OA\Property(property="link", type="string", nullable=true, example="https://example.com/updated-docs", description="Optional link to additional information"),
 *     @OA\Property(property="anonymous", type="boolean", example=true, description="Whether the entry should be displayed anonymously"),
 *     @OA\Property(property="manual_override_prio", type="integer", example=5, description="Manual priority override value")
 * )
 */
class UpdateEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller using policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'problem' => 'sometimes|string',
            'solution' => 'sometimes|string',
            'area' => 'sometimes|string',
            'time_saved_per_year' => 'nullable|integer',
            'gross_profit_per_year' => 'nullable|integer',
            'effort' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'monetary_explanation' => 'sometimes|string',
            'link' => 'nullable|url',
            'anonymous' => 'boolean',
            'manual_override_prio' => 'integer',
        ];
    }
}
