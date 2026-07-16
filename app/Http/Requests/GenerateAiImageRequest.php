<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAiImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('books.create') || $this->user()?->can('books.update');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:1', 'max:2048'],
            'steps' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'seed' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Décrivez l’image à générer.',
            'prompt.max' => 'La description ne peut pas dépasser 2 048 caractères.',
            'steps.min' => 'Le nombre d’étapes doit être compris entre 1 et 8.',
            'steps.max' => 'Le nombre d’étapes doit être compris entre 1 et 8.',
        ];
    }
}
