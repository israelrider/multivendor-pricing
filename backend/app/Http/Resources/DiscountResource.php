<?php

namespace App\Http\Resources;

use App\Discounts\Discount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Discount */
class DiscountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'rule' => $this->rule,
            'label' => $this->label,
            'basis_points' => $this->basisPoints,
        ];
    }
}
