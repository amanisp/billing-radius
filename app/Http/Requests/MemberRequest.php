<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
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
        $mitraId = Auth::user()->group_id;
        return [
            'name' => [
                'required',
                Rule::unique('members')->where(function ($query) use ($mitraId) {
                    return $query->where('group_id', $mitraId);
                }),
            ],
            'phone_number' => 'min:9|nullable|string',
            'email' => 'nullable|string|email',
            'nik' => 'nullable|string|digits:16',
            'address' => 'nullable|string',
        ];
    }
}
