<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remap custom_fields.field_type integer codes from old enum values to new CRMS-aligned values.
 *
 * Old mapping:
 *   0=Text, 1=TextArea, 2=Integer, 3=Decimal, 4=Boolean, 5=Date, 6=DateTime,
 *   7=Time, 8=Select, 9=MultiSelect, 10=Url, 11=Email, 12=Phone, 13=Colour,
 *   14=Currency, 15=Percentage, 16=RichText
 *
 * New mapping:
 *   0=String, 1=Text, 2=Number, 3=Boolean, 4=DateTime, 5=Date, 6=Time,
 *   7=Email, 8=Website, 9=ListOfValues, 10=MultiListOfValues, 11=AutoNumber,
 *   12=Currency, 13=Telephone, 14=FileImage, 15=RichText, 16=JsonKeyValue,
 *   17=Colour, 18=Percentage
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Phase 1: Move conflicting old codes to temporary high values (100+new_code)
            // Process from highest old code to lowest to prevent collisions.
            $phase1 = [
                16 => 115,  // RichText(16) → temp RichText(15)
                15 => 118,  // Percentage(15) → temp Percentage(18)
                14 => 112,  // Currency(14) → temp Currency(12)
                13 => 117,  // Colour(13) → temp Colour(17)
                12 => 113,  // Phone(12) → temp Telephone(13)
                11 => 107,  // Email(11) → temp Email(7)
                10 => 108,  // Url(10) → temp Website(8)
                9 => 110,   // MultiSelect(9) → temp MultiListOfValues(10)
                8 => 109,   // Select(8) → temp ListOfValues(9)
                7 => 106,   // Time(7) → temp Time(6)
                6 => 104,   // DateTime(6) → temp DateTime(4)
                4 => 103,   // Boolean(4) → temp Boolean(3)
                3 => 102,   // Decimal(3) → temp Number(2) — absorb into Number type
            ];

            foreach ($phase1 as $oldCode => $tempCode) {
                DB::table('custom_fields')
                    ->where('field_type', $oldCode)
                    ->update(['field_type' => $tempCode]);
            }

            // Phase 2: Move temp codes to final new codes
            foreach ($phase1 as $tempCode) {
                $newCode = $tempCode - 100;
                DB::table('custom_fields')
                    ->where('field_type', $tempCode)
                    ->update(['field_type' => $newCode]);
            }

            // Codes 0 (String), 1 (Text), 2 (Number), 5 (Date) stay in same slots — no action needed.

            // Phase 3: Migrate data columns for type changes
            // Old Integer(2) stored in value_integer; new Number(2) uses value_decimal.
            // CAST works on both SQLite and PostgreSQL.
            DB::table('custom_field_values')
                ->whereIn('custom_field_id', function ($q) {
                    $q->select('id')->from('custom_fields')->where('field_type', 2);
                })
                ->whereNotNull('value_integer')
                ->update([
                    'value_decimal' => DB::raw('CAST(value_integer AS REAL)'),
                    'value_integer' => null,
                ]);

            // Old Decimal(3) fields are now Number(2) — data already in value_decimal, no column change needed.

            // Old Select(8) stored display names in value_string; new ListOfValues(9) stores list_value_id in value_integer.
            // Resolve display names to list_value_ids using per-row subquery (portable SQL).
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('
                    UPDATE custom_field_values
                    SET value_integer = lv.id, value_string = NULL
                    FROM custom_fields cf, list_values lv
                    WHERE custom_field_values.custom_field_id = cf.id
                    AND lv.list_name_id = cf.list_name_id
                    AND lv.name = custom_field_values.value_string
                    AND cf.field_type = 9
                    AND custom_field_values.value_string IS NOT NULL
                ');
            } else {
                // SQLite-compatible: use correlated subquery
                $rows = DB::table('custom_field_values as cfv')
                    ->join('custom_fields as cf', 'cfv.custom_field_id', '=', 'cf.id')
                    ->where('cf.field_type', 9)
                    ->whereNotNull('cfv.value_string')
                    ->select('cfv.id', 'cfv.value_string', 'cf.list_name_id')
                    ->get();

                foreach ($rows as $row) {
                    $listValueId = DB::table('list_values')
                        ->where('list_name_id', $row->list_name_id)
                        ->where('name', $row->value_string)
                        ->value('id');

                    if ($listValueId) {
                        DB::table('custom_field_values')
                            ->where('id', $row->id)
                            ->update(['value_integer' => $listValueId, 'value_string' => null]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Reverse data column changes first
            // ListOfValues(9) value_integer back to value_string
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('
                    UPDATE custom_field_values
                    SET value_string = lv.name, value_integer = NULL
                    FROM custom_fields cf, list_values lv
                    WHERE custom_field_values.custom_field_id = cf.id
                    AND lv.id = custom_field_values.value_integer
                    AND cf.field_type = 9
                    AND custom_field_values.value_integer IS NOT NULL
                ');
            } else {
                $rows = DB::table('custom_field_values as cfv')
                    ->join('custom_fields as cf', 'cfv.custom_field_id', '=', 'cf.id')
                    ->where('cf.field_type', 9)
                    ->whereNotNull('cfv.value_integer')
                    ->select('cfv.id', 'cfv.value_integer')
                    ->get();

                foreach ($rows as $row) {
                    $name = DB::table('list_values')->where('id', $row->value_integer)->value('name');
                    if ($name) {
                        DB::table('custom_field_values')
                            ->where('id', $row->id)
                            ->update(['value_string' => $name, 'value_integer' => null]);
                    }
                }
            }

            // Number(2) value_decimal back to value_integer
            DB::table('custom_field_values')
                ->whereIn('custom_field_id', function ($q) {
                    $q->select('id')->from('custom_fields')->where('field_type', 2);
                })
                ->whereNotNull('value_decimal')
                ->update([
                    'value_integer' => DB::raw('CAST(value_decimal AS INTEGER)'),
                    'value_decimal' => null,
                ]);

            // Reverse code mapping: move to temp first, then to old codes
            $reverse = [
                2 => 103,   // Number(2) that were Decimal → temp for old Decimal(3)
                3 => 104,   // Boolean(3) → temp for old Boolean(4)
                4 => 106,   // DateTime(4) → temp for old DateTime(6)
                6 => 107,   // Time(6) → temp for old Time(7)
                7 => 111,   // Email(7) → temp for old Email(11)
                8 => 110,   // Website(8) → temp for old Url(10)
                9 => 108,   // ListOfValues(9) → temp for old Select(8)
                10 => 109,  // MultiListOfValues(10) → temp for old MultiSelect(9)
                12 => 114,  // Currency(12) → temp for old Currency(14)
                13 => 112,  // Telephone(13) → temp for old Phone(12)
                15 => 116,  // RichText(15) → temp for old RichText(16)
                17 => 113,  // Colour(17) → temp for old Colour(13)
                18 => 115,  // Percentage(18) → temp for old Percentage(15)
            ];

            foreach ($reverse as $newCode => $tempCode) {
                DB::table('custom_fields')
                    ->where('field_type', $newCode)
                    ->update(['field_type' => $tempCode]);
            }

            foreach ($reverse as $tempCode) {
                $oldCode = $tempCode - 100;
                DB::table('custom_fields')
                    ->where('field_type', $tempCode)
                    ->update(['field_type' => $oldCode]);
            }
        });
    }
};
