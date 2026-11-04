<?php

namespace App\Enums;

enum OrderStatus: string
{
    /** Created; at least one vendor has not been notified yet. */
    case Pending = 'pending';

    /** Every vendor sub-order has been notified. */
    case Notified = 'notified';
}
