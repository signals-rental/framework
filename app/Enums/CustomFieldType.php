<?php

namespace App\Enums;

enum CustomFieldType: int
{
    case String = 0;
    case Text = 1;
    case Number = 2;
    case Boolean = 3;
    case DateTime = 4;
    case Date = 5;
    case Time = 6;
    case Email = 7;
    case Website = 8;
    case ListOfValues = 9;
    case MultiListOfValues = 10;
    case AutoNumber = 11;
    case Currency = 12;
    case Telephone = 13;
    case FileImage = 14;
    case RichText = 15;
    case JsonKeyValue = 16;
    case Colour = 17;
    case Percentage = 18;

    public function label(): string
    {
        return match ($this) {
            self::String => 'String',
            self::Text => 'Text Area',
            self::Number => 'Number',
            self::Boolean => 'Boolean',
            self::DateTime => 'Date & Time',
            self::Date => 'Date',
            self::Time => 'Time',
            self::Email => 'Email',
            self::Website => 'Website',
            self::ListOfValues => 'List of Values',
            self::MultiListOfValues => 'Multi List of Values',
            self::AutoNumber => 'Auto Number',
            self::Currency => 'Currency',
            self::Telephone => 'Telephone',
            self::FileImage => 'File / Image',
            self::RichText => 'Rich Text',
            self::JsonKeyValue => 'JSON Key-Value',
            self::Colour => 'Colour',
            self::Percentage => 'Percentage',
        };
    }

    /**
     * Get the value column used for EAV storage.
     */
    public function valueColumn(): string
    {
        return match ($this) {
            self::String, self::Email, self::Website, self::Telephone, self::AutoNumber, self::Colour => 'value_string',
            self::Text, self::RichText => 'value_text',
            self::ListOfValues => 'value_integer',
            self::Number, self::Currency, self::Percentage => 'value_decimal',
            self::Boolean => 'value_boolean',
            self::Date => 'value_date',
            self::DateTime => 'value_datetime',
            self::Time => 'value_time',
            self::MultiListOfValues, self::FileImage, self::JsonKeyValue => 'value_json',
        };
    }
}
