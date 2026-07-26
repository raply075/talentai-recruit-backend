<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CoverLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resume_id' => [
                'required',
                'exists:resumes,id',
            ],

            'company' => [
                'required',
                'string',
                'max:255',
            ],

            'position' => [
                'required',
                'string',
                'max:255',
            ],

            'tone' => [
                'nullable',
                'in:professional,friendly,formal,confident',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'resume_id.required' => 'Resume wajib dipilih.',
            'resume_id.exists'   => 'Resume tidak ditemukan.',

            'company.required'   => 'Nama perusahaan wajib diisi.',
            'company.max'        => 'Nama perusahaan maksimal 255 karakter.',

            'position.required'  => 'Posisi yang dilamar wajib diisi.',
            'position.max'       => 'Nama posisi maksimal 255 karakter.',

            'tone.in'            => 'Tone tidak valid.',
        ];
    }
}