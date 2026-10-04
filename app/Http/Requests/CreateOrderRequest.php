<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Amount in rupees from the client; converted to paise in the controller.
            // In a real shop the amount comes from the cart/product on the SERVER,
            // never trusted from the browser.
            'amount' => ['required', 'numeric', 'min:1', 'max:500000'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
        ];
    }
}
