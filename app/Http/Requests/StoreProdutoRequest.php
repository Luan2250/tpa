<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProdutoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
    return true;
        }

        public function rules(): array
        {
            return [
                'nome'           => ['required', 'string', 'max:255'],
                'categoria'      => ['required', 'in:Eletrônicos,Vestuário,Casa,Alimentos'],
                'garantia_meses' => ['required', 'integer', 'between:0,36'],
                'preco'          => ['required', 'numeric', 'gt:0'],
                'estoque'        => ['required', 'integer', 'min:0'],
            ];
        }
}
