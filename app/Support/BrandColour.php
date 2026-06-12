<?php

namespace App\Support;

/**
 * Pure colour utilities for brand-colour contrast safety.
 *
 * All methods are static and side-effect free. They operate on hex strings and
 * apply the WCAG 2.x relative-luminance / contrast-ratio formulas so the UI can
 * guarantee readable text and active-state indicators regardless of the brand
 * colours an operator picks.
 */
final class BrandColour
{
    /**
     * Fallback brand colours (the stock navy / green theme). Used whenever a
     * supplied hex value cannot be parsed.
     */
    public const DEFAULT_PRIMARY = '#1e3a5f';

    public const DEFAULT_ACCENT = '#059669';

    /**
     * Near-black ink used by the design language for "dark text on light".
     */
    private const INK_DARK = '#0f172a';

    private const INK_LIGHT = '#ffffff';

    /**
     * WCAG relative luminance of a colour (0.0 black → 1.0 white).
     */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);

        $channels = array_map(static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }, [$r, $g, $b]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * WCAG contrast ratio between two colours (1.0 → 21.0).
     */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        $lighter = max($la, $lb);
        $darker = min($la, $lb);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Return a readable "ink" version of $hex for use as text/heading/border on
     * the $against surface.
     *
     * If the colour already meets $target contrast it is returned unchanged.
     * Otherwise it is darkened stepwise (mixed toward black, preserving hue)
     * until the target is met. A hard floor guarantees the returned colour
     * always meets the target — in the worst case pure black is returned.
     */
    public static function ink(string $hex, string $against = '#ffffff', float $target = 4.5): string
    {
        $normalised = self::normalise($hex);

        if (self::contrast($normalised, $against) >= $target) {
            return $normalised;
        }

        [$r, $g, $b] = self::rgb($normalised);

        // Darken toward black in small steps, preserving hue (a uniform scale of
        // the RGB channels keeps the chroma ratios, i.e. the hue, intact).
        for ($factor = 0.95; $factor >= 0.0; $factor -= 0.05) {
            $candidate = self::toHex(
                (int) round($r * $factor),
                (int) round($g * $factor),
                (int) round($b * $factor),
            );

            if (self::contrast($candidate, $against) >= $target) {
                return $candidate;
            }
        }

        // Hard floor: black has the maximum possible contrast against any
        // non-black surface, so this always satisfies a reasonable target.
        return '#000000';
    }

    /**
     * Minimum contrast at which white foreground is considered acceptable on a
     * coloured fill. White-on-fill is the established convention for the bold,
     * large button/badge text these fills carry, so we keep white unless it is
     * genuinely unreadable (pale fills) — at which point we fall back to the
     * higher-contrast option. This keeps the stock navy/green theme's white
     * button text unchanged while still protecting against pale brand colours.
     */
    private const ON_WHITE_FLOOR = 2.6;

    /**
     * Return the best foreground colour ('#0f172a' near-black, or '#ffffff'
     * white) to place ON TOP of the given background colour.
     *
     * White is preferred whenever it clears the readability floor (matching the
     * existing design language of white-on-brand fills); only genuinely pale
     * fills fall through to whichever ink has the higher contrast.
     */
    public static function on(string $hex): string
    {
        $normalised = self::normalise($hex);

        if (self::contrast($normalised, self::INK_LIGHT) >= self::ON_WHITE_FLOOR) {
            return self::INK_LIGHT;
        }

        return self::contrast($normalised, self::INK_DARK) >= self::contrast($normalised, self::INK_LIGHT)
            ? self::INK_DARK
            : self::INK_LIGHT;
    }

    /**
     * Normalise a hex colour to lowercase 6-digit `#rrggbb`, falling back to the
     * stock navy default when the input cannot be parsed.
     */
    public static function normalise(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return self::toHex($r, $g, $b);
    }

    /**
     * Parse a hex string (3- or 6-digit, with or without leading #) into an
     * [r, g, b] tuple. Invalid input falls back to the default primary colour.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $value = ltrim(trim($hex), '#');

        if (strlen($value) === 3 && ctype_xdigit($value)) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        if (strlen($value) !== 6 || ! ctype_xdigit($value)) {
            // Recurse once into the known-good default; guard against infinite
            // recursion by only doing so when we are not already the default.
            $default = ltrim(self::DEFAULT_PRIMARY, '#');

            return $value === $default
                ? [0, 0, 0]
                : self::rgb(self::DEFAULT_PRIMARY);
        }

        return [
            (int) hexdec(substr($value, 0, 2)),
            (int) hexdec(substr($value, 2, 2)),
            (int) hexdec(substr($value, 4, 2)),
        ];
    }

    private static function toHex(int $r, int $g, int $b): string
    {
        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b)),
        );
    }
}
