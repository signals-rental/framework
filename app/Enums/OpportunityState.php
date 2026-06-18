<?php

namespace App\Enums;

/**
 * The document-type axis of the two-axis opportunity model.
 *
 * State answers "what kind of document is this?" — orthogonal to status, which
 * answers "where is it in the workflow?". Values are RMS-aligned integers and
 * are persisted on `opportunities.state`.
 *
 * @see OpportunityStatus
 */
enum OpportunityState: int
{
    case Draft = 0;

    case Quotation = 1;

    case Order = 2;

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Quotation => 'Quotation',
            self::Order => 'Order',
        };
    }

    /**
     * The status a freshly-entered opportunity in this state takes by default:
     * Draft → Open, Quotation → Provisional, Order → Active.
     */
    public function defaultStatus(): OpportunityStatus
    {
        return match ($this) {
            self::Draft => OpportunityStatus::DraftOpen,
            self::Quotation => OpportunityStatus::QuotationProvisional,
            self::Order => OpportunityStatus::OrderActive,
        };
    }
}
