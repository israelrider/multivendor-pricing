<?php

namespace App\Http\Requests;

/**
 * The same cart as a quote, plus the total the customer saw on the review screen.
 * If it no longer matches the server's price the API answers 409 (README section 5).
 */
class StoreOrderRequest extends QuoteCartRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'expected_total_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function expectedTotalCents(): ?int
    {
        $value = $this->validated('expected_total_cents');

        return $value === null ? null : (int) $value;
    }
}
