<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Helpers\GovAiIndexHelper;
use Illuminate\Foundation\Http\FormRequest;

class AiPolicyTrackerPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $govAiIndexValues = array_column(GovAiIndexHelper::$govAiIndex, 'value');
        return [
            "gov_ai_index" => ["required", Rule::in($govAiIndexValues)],
            // "gov_ai_index_name" => "nullable|required_if:gov_ai_index,strategy|string|max:50",
            // "ai_policy_name" => "nullable|required_if:gov_ai_index,policy|string|max:255",
            "ai_policy_name" => 'required',
            "country_id" => "required|string|exists:countries,id",
            "status_id" => "required|string|exists:statuses,id",
            "governing_body" => "sometimes|nullable|string",
            "announcement_year" => "sometimes|nullable|date",
            "whitepaper_document_link" => "sometimes|nullable|string",
            "technology_partners" => "sometimes|nullable|string",
            "governance_structure" => "sometimes|nullable|string",
            "main_motivation" => "sometimes|nullable|string",
            "description" => "nullable",
        ];
    }

    public function messages()
    {
        return [
            "ai_policy_name.required" => "This field is required.",
        ];
    }
}
