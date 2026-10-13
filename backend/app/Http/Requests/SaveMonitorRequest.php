<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use App\Models\Monitor;
use App\Rules\SafePublicUrl;
use Illuminate\Validation\Rule;
use App\Enums\HttpMethod;
use Closure;

class SaveMonitorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $monitor = $this->route('monitor');
 
        Gate::authorize($monitor ? 'update' : 'create', $monitor ?? Monitor::class);
 
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $plan = $this->user()->plan();
 
        return [
            'name' => [$required, 'string', 'max:255'],
            'url' => [$required, 'bail', 'string', 'max:2048', 'url:http,https', new SafePublicUrl],
            'method' => ['sometimes', Rule::enum(HttpMethod::class)],
            'expected_status_code' => ['sometimes', 'nullable', 'integer', 'between:100,599'],
            'interval_seconds' => [$required, 'integer', Rule::in($plan->allowedIntervals())],
            'timeout_seconds' => ['sometimes', 'integer', 'between:1,60'],
            'request_headers' => [
                'sometimes', 'bail', 'nullable', 'array', 'max:10',
                function (string $attribute, mixed $value, Closure $fail) {
                    foreach (array_keys($value) as $name) {
                        if (! preg_match('/^[A-Za-z0-9-]{1,64}$/', (string) $name)) {
                            $fail('Header names may only contain letters, digits and dashes.');
 
                            return;
                        }
                    }
                },
            ],
            'request_headers.*' => ['string', 'max:1000'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'interval_seconds.in' => 'This check interval is not available on your current plan.',
        ];
    }
}
