<?php

namespace App\Http\Requests;

/**
 * The same cart as a quote, plus the total the customer saw on the review screen.
 * If it no longer matches the server's price the API answers 409.
 *
 * The optional `Idempotency-Key` header is validated here too, under the `idempotency_key` key,
 * so a bad key gets the usual 422 shape. Same key and same cart → the same order.
 */
class StoreOrderRequest extends QuoteCartRequest
{
    public const string IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public const int MAX_IDEMPOTENCY_KEY_LENGTH = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'expected_total_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            // Printable ASCII without spaces: a UUID, a ULID or any opaque token fits.
            'idempotency_key' => ['nullable', 'string', 'max:'.self::MAX_IDEMPOTENCY_KEY_LENGTH, 'regex:/^[\x21-\x7E]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'idempotency_key.max' => 'The Idempotency-Key header may not be longer than :max characters.',
            'idempotency_key.regex' => 'The Idempotency-Key header may only contain printable ASCII characters without spaces.',
        ];
    }

    /** The header is validated alongside the body. A body field with the same name is ignored. */
    public function validationData(): array
    {
        return [...parent::validationData(), 'idempotency_key' => $this->header(self::IDEMPOTENCY_HEADER)];
    }

    public function expectedTotalCents(): ?int
    {
        $value = $this->validated('expected_total_cents');

        return $value === null ? null : (int) $value;
    }

    public function idempotencyKey(): ?string
    {
        $value = $this->validated('idempotency_key');

        return $value === null || $value === '' ? null : (string) $value;
    }
}
