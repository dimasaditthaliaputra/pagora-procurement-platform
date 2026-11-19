<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kind of entry in the negotiation chat (`negotiation_messages.type`, design-db §4.17).
 */
enum NegotiationMessageType: string
{
    case Text = 'text';
    case Offer = 'offer';
    case Acceptance = 'acceptance';
    case ItemRemoved = 'item_removed';
    case System = 'system';
}
