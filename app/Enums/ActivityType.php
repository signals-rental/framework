<?php

namespace App\Enums;

enum ActivityType: int
{
    case Task = 1001;
    case Call = 1002;
    case Fax = 1003;
    case Email = 1004;
    case Meeting = 1005;
    case Note = 1006;
    case Letter = 1007;

    public function label(): string
    {
        return match ($this) {
            self::Task => 'Task',
            self::Call => 'Call',
            self::Fax => 'Fax',
            self::Email => 'Email',
            self::Meeting => 'Meeting',
            self::Note => 'Note',
            self::Letter => 'Letter',
        };
    }

    public static function fromCrmsName(string $name): self
    {
        return match (strtolower($name)) {
            'task' => self::Task,
            'call' => self::Call,
            'fax' => self::Fax,
            'email' => self::Email,
            'meeting' => self::Meeting,
            'note' => self::Note,
            'letter' => self::Letter,
            default => throw new \ValueError("Unknown CRMS activity type: {$name}"),
        };
    }
}
