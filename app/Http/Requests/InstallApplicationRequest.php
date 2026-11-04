<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Services\Installation\InstallationReadiness;

class InstallApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usingExistingAdmin = app(InstallationReadiness::class)->bootstrapAdminCandidate() !== null;

        return [
            'admin_name' => [$usingExistingAdmin ? 'nullable' : 'required', 'string', 'max:100'],
            'admin_email' => ['required', 'string', 'email:rfc', 'max:100'],
            'admin_password' => $usingExistingAdmin ? ['nullable'] : ['required', 'confirmed', Password::defaults()],
            'locale' => ['nullable', 'string', Rule::in(array_keys(config('app.supported_locales', [])))],
            'school_language_preset' => ['nullable', 'string', 'in:home_sections,subject_schedule,hybrid'],
            'organization_name' => ['required', 'string', 'max:255'],
            'campus_name' => ['required', 'string', 'max:255'],
            'campus_country' => ['sometimes', 'in:United States'],
            'campus_state' => ['sometimes', 'in:California'],
            'campus_initials' => ['nullable', 'string', 'max:10'],
            'campus_email' => ['nullable', 'email:rfc', 'max:255'],
            'load_demo_data' => ['sometimes', 'boolean'],
        ];
    }
}
