<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resume_id' => ['required', 'integer', 'exists:resumes,id'],
            'position' => ['required', 'string', 'max:255'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'question_count' => ['required', 'integer', 'min:5', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'resume_id.required' => 'Resume wajib dipilih.',
            'resume_id.exists' => 'Resume tidak ditemukan.',
            'position.required' => 'Posisi yang dilamar wajib diisi.',
            'difficulty.required' => 'Tingkat kesulitan wajib dipilih.',
            'difficulty.in' => 'Difficulty harus easy, medium, atau hard.',
            'question_count.required' => 'Jumlah pertanyaan wajib diisi.',
            'question_count.min' => 'Minimal 5 pertanyaan.',
            'question_count.max' => 'Maksimal 20 pertanyaan.',
        ];
    }
}