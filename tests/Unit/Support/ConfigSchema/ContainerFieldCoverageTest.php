<?php

use App\Support\ConfigSchema\ContainerField;
use App\Support\ConfigSchema\Field;
use App\Support\ConfigSchema\Fields\DecimalField;
use App\Support\ConfigSchema\Fields\RepeaterField;

/**
 * A minimal concrete ContainerField that surfaces the protected no-op hooks so
 * the documented base behaviour (leaf type-rule + cast are no-ops for fields
 * that only contain other fields) can be asserted directly. Built inline in each
 * test so the analyser resolves the anonymous subclass's public probe methods.
 */
describe('ContainerField no-op leaf hooks', function () {
    it('produces no type rules (it holds child fields, not a scalar value)', function () {
        $probe = new class('probe') extends ContainerField
        {
            public function type(): string
            {
                return 'probe';
            }

            /** @return array<int, mixed> */
            public function exposedTypeRules(): array
            {
                return $this->typeRules();
            }
        };

        expect($probe->exposedTypeRules())->toBe([]);
    });

    it('passes a cast value straight through unchanged', function () {
        $probe = new class('probe') extends ContainerField
        {
            public function type(): string
            {
                return 'probe';
            }

            public function exposedCastValue(mixed $value): mixed
            {
                return $this->castValue($value);
            }
        };

        expect($probe->exposedCastValue(['a' => 1]))->toBe(['a' => 1]);
    });
});

describe('RepeaterField rejects per-row conditional visibility', function () {
    it('throws when a row field declares visibleWhen()', function () {
        expect(fn () => RepeaterField::make('tiers')->fields(
            DecimalField::make('multiplier')->visibleWhen('enabled', true),
        ))->toThrow(
            InvalidArgumentException::class,
            'Repeater row fields cannot use visibleWhen(); per-row conditional visibility is not supported.',
        );
    });

    it('accepts plain row fields without visibility conditions', function () {
        $repeater = RepeaterField::make('tiers')->fields(
            DecimalField::make('multiplier')->required(),
        );

        expect($repeater->getFields())->toHaveCount(1)
            ->and($repeater->getFields()[0])->toBeInstanceOf(Field::class);
    });
});
