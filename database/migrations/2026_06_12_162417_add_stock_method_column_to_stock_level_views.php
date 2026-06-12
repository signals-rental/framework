<?php

use App\Models\CustomView;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Surface the serialised/bulk "Method" column in existing stock-level
     * views by inserting its key after the serial number column.
     */
    public function up(): void
    {
        foreach (CustomView::where('entity_type', 'stock_levels')->get() as $view) {
            $columns = $view->columns ?? [];

            if (in_array('stock_method', $columns, true)) {
                continue;
            }

            $position = array_search('serial_number', $columns, true);
            if ($position === false) {
                $columns[] = 'stock_method';
            } else {
                array_splice($columns, $position + 1, 0, 'stock_method');
            }

            $view->columns = $columns;
            $view->save();
        }
    }

    public function down(): void
    {
        foreach (CustomView::where('entity_type', 'stock_levels')->get() as $view) {
            $columns = array_values(array_filter(
                $view->columns ?? [],
                fn (string $key): bool => $key !== 'stock_method',
            ));

            $view->columns = $columns;
            $view->save();
        }
    }
};
