<?php

namespace App\Contracts;

use App\Services\SchemaBuilder;

interface HasSchema
{
    public static function defineSchema(SchemaBuilder $builder): void;
}
