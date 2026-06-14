<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\ListName;
use App\Models\ListValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert activities.type_id from the int-backed ActivityType enum
     * (1001..1007) into a reference to the user-extensible "Activity Type"
     * list-of-values, mirroring the Address.type_id pattern.
     */
    public function up(): void
    {
        // 1. Ensure the "Activity Type" list + its values exist so this
        //    migration is self-contained (works before/independent of the seeder).
        $list = ListName::query()->firstOrCreate(
            ['name' => 'Activity Type'],
            [
                'description' => 'Activity Type options',
                'is_system' => true,
            ],
        );

        /** @var array<int, int> $oldToNew Map old enum int (1001..1007) → new list_value id. */
        $oldToNew = [];

        foreach (ActivityType::cases() as $index => $case) {
            $value = ListValue::query()->firstOrCreate(
                ['list_name_id' => $list->id, 'name' => $case->label()],
                [
                    'sort_order' => $index,
                    'is_system' => true,
                    'is_active' => true,
                    'metadata' => ['icon' => $case->icon()],
                ],
            );

            $oldToNew[$case->value] = $value->id;
        }

        // 2. Remap existing rows from old enum ints to new list_value ids.
        foreach ($oldToNew as $oldInt => $newId) {
            Activity::query()->where('type_id', $oldInt)->update(['type_id' => $newId]);
        }

        // 2b. Null out any dirty/legacy type_id values (e.g. 0, 9999) that did not
        //     map to a known enum int. They point at no list_value and would either
        //     abort the FK validation on PostgreSQL or silently orphan on SQLite.
        //     type_id is nullable, so null satisfies the constraint added below.
        Activity::query()
            ->whereNotIn('type_id', array_values($oldToNew))
            ->whereNotNull('type_id')
            ->update(['type_id' => null]);

        // 3. Re-declare the column as a constrained, nullable FK to list_values
        //    (mirrors Address.type_id). Drop the old default(1001).
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropIndex(['type_id']);
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->unsignedBigInteger('type_id')->nullable()->default(null)->change();
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->foreign('type_id')->references('id')->on('list_values')->nullOnDelete();
            $table->index('type_id');
        });
    }

    /**
     * Best-effort reverse: map list_value ids back to old enum ints, restore the
     * plain integer column with its original default.
     */
    public function down(): void
    {
        $list = ListName::query()->where('name', 'Activity Type')->first();

        /** @var array<int, int> $newToOld Map list_value id → old enum int. */
        $newToOld = [];

        if ($list !== null) {
            foreach (ActivityType::cases() as $case) {
                $value = ListValue::query()
                    ->where('list_name_id', $list->id)
                    ->where('name', $case->label())
                    ->first();

                if ($value !== null) {
                    $newToOld[$value->id] = $case->value;
                }
            }
        }

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropForeign(['type_id']);
            $table->dropIndex(['type_id']);
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->integer('type_id')->default(1001)->change();
        });

        foreach ($newToOld as $newId => $oldInt) {
            Activity::query()->where('type_id', $newId)->update(['type_id' => $oldInt]);
        }

        // Coalesce any rows still holding a list_value id that has no matching
        // ActivityType case (e.g. user-added types, or a nulled type_id) to the
        // Task enum int. The reverted column is read through the int-backed
        // ActivityType cast, which would throw a ValueError on an invalid int.
        Activity::query()
            ->whereNotIn('type_id', array_keys($newToOld))
            ->update(['type_id' => ActivityType::Task->value]);

        Schema::table('activities', function (Blueprint $table): void {
            $table->index('type_id');
        });
    }
};
