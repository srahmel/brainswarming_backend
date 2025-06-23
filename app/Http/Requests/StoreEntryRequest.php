<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="StoreEntryRequest",
 *     title="Store Entry Request",
 *     description="Request for creating a new entry",
 *     required={"problem", "solution", "area", "effort", "monetary_explanation"},
 *     @OA\Property(property="problem", type="string", example="Slow customer response time", description="Description of the problem"),
 *     @OA\Property(property="solution", type="string", example="Implement automated response system", description="Proposed solution to the problem"),
 *     @OA\Property(property="area", type="string", example="Customer Service", description="Business area affected by the problem/solution"),
 *     @OA\Property(property="time_saved_per_year", type="integer", nullable=true, example=500, description="Estimated time saved per year in hours"),
 *     @OA\Property(property="gross_profit_per_year", type="integer", nullable=true, example=10000, description="Estimated gross profit increase per year"),
 *     @OA\Property(property="effort", type="string", enum={"low", "medium", "high"}, example="medium", description="Estimated effort to implement the solution"),
 *     @OA\Property(property="monetary_explanation", type="string", example="Saves 500 hours per year at $20/hour", description="Explanation of the monetary benefits"),
 *     @OA\Property(property="link", type="string", nullable=true, example="https://example.com/docs", description="Optional link to additional information"),
 *     @OA\Property(property="anonymous", type="boolean", example=false, description="Whether the entry should be displayed anonymously"),
 *     @OA\Property(property="manual_override_prio", type="integer", example=0, description="Manual priority override value")
 * )
 */
class StoreEntryRequest extends FormRequest
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
            'problem' => 'required|string',
            'solution' => 'required|string',
            'area' => 'required|string',
            'time_saved_per_year' => 'nullable|integer',
            'gross_profit_per_year' => 'nullable|integer',
            'effort' => ['required', Rule::in(['low', 'medium', 'high'])],
            'monetary_explanation' => 'required|string',
            'link' => 'nullable|url',
            'anonymous' => 'boolean',
            'manual_override_prio' => 'integer',
        ];
    }
}
