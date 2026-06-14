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

    /**
     * Stable icon key per default activity type. Persisted in each seeded
     * ListValue's metadata (`['icon' => ...]`) so the calendar can render the
     * right glyph regardless of the user-chosen list value name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Task => 'task',
            self::Call => 'call',
            self::Fax => 'fax',
            self::Email => 'email',
            self::Meeting => 'meeting',
            self::Note => 'note',
            self::Letter => 'letter',
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
            default => throw new \ValueError("Unknown RMS activity type: {$name}"),
        };
    }
}
