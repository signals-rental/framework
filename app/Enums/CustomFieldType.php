<?php

namespace App\Enums;

enum CustomFieldType: int
{
    case Text = 0;
    case TextArea = 1;
    case Integer = 2;
    case Decimal = 3;
    case Boolean = 4;
    case Date = 5;
    case DateTime = 6;
    case Time = 7;
    case Select = 8;
    case MultiSelect = 9;
    case Url = 10;
    case Email = 11;
    case Phone = 12;
    case Colour = 13;
    case Currency = 14;
    case Percentage = 15;
    case RichText = 16;

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::TextArea => 'Text Area',
            self::Integer => 'Integer',
            self::Decimal => 'Decimal',
            self::Boolean => 'Boolean',
            self::Date => 'Date',
            self::DateTime => 'Date & Time',
            self::Time => 'Time',
            self::Select => 'Select',
            self::MultiSelect => 'Multi Select',
            self::Url => 'URL',
            self::Email => 'Email',
            self::Phone => 'Phone',
            self::Colour => 'Colour',
            self::Currency => 'Currency',
            self::Percentage => 'Percentage',
            self::RichText => 'Rich Text',
        };
    }

    /**
     * Get the value column used for EAV storage.
     */
    public function valueColumn(): string
    {
        return match ($this) {
            self::Text, self::Url, self::Email, self::Phone, self::Colour => 'value_string',
            self::TextArea, self::RichText => 'value_text',
            self::Integer => 'value_integer',
            self::Decimal, self::Currency, self::Percentage => 'value_decimal',
            self::Boolean => 'value_boolean',
            self::Date => 'value_date',
            self::DateTime => 'value_datetime',
            self::Time => 'value_time',
            self::Select => 'value_string',
            self::MultiSelect => 'value_json',
        };
    }
}
