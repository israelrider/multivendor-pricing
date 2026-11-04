<?php

namespace App\Enums;

enum SubOrderStatus: string
{
    /** Created; waiting for NotifyVendorJob. */
    case Pending = 'pending';

    /** NotifyVendorJob has run; notified_at is set. */
    case Notified = 'notified';
}
