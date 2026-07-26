<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResumeUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

   public function rules(): array
{
    return [
        'resume' => [
            'required',
            'file',
            'mimes:pdf,doc,docx',
            'max:10240',
        ],
    ];
}
}