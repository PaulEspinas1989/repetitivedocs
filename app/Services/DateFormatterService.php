<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Detects and applies date formatting for template variables.
 *
 * Why this exists:
 *   HTML5 <input type="date"> always submits YYYY-MM-DD regardless of locale.
 *   Philippine government documents use formats like "May 30, 2026" or "30 May 2026".
 *   We detect the original document's format from the AI-extracted example_value
 *   and store it as a PHP date format string, then format submissions accordingly.
 *
 * Default format: 'F j, Y'  →  "May 30, 2026"
 */
class DateFormatterService
{
    const DEFAULT_FORMAT = 'F j, Y';

    /**
     * Detect a PHP date format string from an example date string.
     *
     * Examples:
     *   "May 30, 2026"   → 'F j, Y'
     *   "30 May 2026"    → 'j F Y'
     *   "May 30th, 2026" → 'F j, Y'  (ordinal stripped)
     *   "05/30/2026"     → 'm/d/Y'
     *   "30/05/2026"     → 'd/m/Y'
     *   "2026-05-30"     → 'Y-m-d'
     *   "30-05-2026"     → 'd-m-Y'
     *   "May 2026"       → 'F Y'
     */
    public function detectFormat(string $exampleDate): string
    {
        $s = trim($exampleDate);

        // ISO: 2026-05-30
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return 'Y-m-d';
        }

        // "May 30, 2026" or "May 30th, 2026"
        if (preg_match('/^[A-Za-z]+ \d{1,2}(?:st|nd|rd|th)?,\s*\d{4}$/', $s)) {
            return 'F j, Y';
        }

        // "30 May 2026"
        if (preg_match('/^\d{1,2} [A-Za-z]+ \d{4}$/', $s)) {
            return 'j F Y';
        }

        // "May 2026" (month + year only)
        if (preg_match('/^[A-Za-z]+ \d{4}$/', $s)) {
            return 'F Y';
        }

        // Slash formats — need to distinguish MM/DD vs DD/MM
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
            // If first part > 12, it must be day-first
            if ((int) $m[1] > 12) {
                return 'd/m/Y';
            }
            // If second part > 12, it must be month-first
            if ((int) $m[2] > 12) {
                return 'm/d/Y';
            }
            // Ambiguous — default to MM/DD/YYYY for US-style, but Philippine docs often DD/MM
            // Use day-first for Philippine government documents
            return 'd/m/Y';
        }

        // Dot formats: 30.05.2026
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $s, $m)) {
            return (int) $m[1] > 12 ? 'd.m.Y' : 'm.d.Y';
        }

        // Dash formats: 30-05-2026
        if (preg_match('/^(\d{1,2})-(\d{2})-(\d{4})$/', $s, $m)) {
            return (int) $m[1] > 12 ? 'd-m-Y' : 'm-d-Y';
        }

        return self::DEFAULT_FORMAT;
    }

    /**
     * Format a raw date value (which may be ISO YYYY-MM-DD or any parseable date)
     * into the target PHP date format string.
     *
     * Returns the formatted string, or the original value if parsing fails.
     */
    public function format(string $rawValue, ?string $targetFormat = null): string
    {
        $format = $targetFormat ?: self::DEFAULT_FORMAT;

        // If the raw value already matches the target format, return as-is
        // (avoids double-formatting if someone passes a pre-formatted date)
        if ($format !== 'Y-m-d' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawValue)) {
            // Try to parse as a non-ISO date and reformat
            try {
                $dt = Carbon::parse($rawValue);
                return $dt->format($format);
            } catch (\Throwable) {
                return $rawValue;
            }
        }

        // Parse ISO YYYY-MM-DD
        try {
            $dt = Carbon::createFromFormat('Y-m-d', $rawValue);
            if (!$dt) {
                return $rawValue;
            }
            return $dt->format($format);
        } catch (\Throwable) {
            return $rawValue;
        }
    }

    /**
     * Format a raw ISO date for display in the UI preview.
     * Always uses the human-readable default regardless of stored format.
     */
    public function formatForPreview(string $isoDate): string
    {
        return $this->format($isoDate, self::DEFAULT_FORMAT);
    }

    /**
     * Return a human-readable description of a PHP date format string.
     * Used in tooltips / preview labels.
     */
    public function describeFormat(string $format): string
    {
        return match ($format) {
            'F j, Y'  => 'May 30, 2026',
            'j F Y'   => '30 May 2026',
            'F Y'     => 'May 2026',
            'Y-m-d'   => '2026-05-30',
            'm/d/Y'   => '05/30/2026',
            'd/m/Y'   => '30/05/2026',
            'd.m.Y'   => '30.05.2026',
            'm.d.Y'   => '05.30.2026',
            'd-m-Y'   => '30-05-2026',
            'm-d-Y'   => '05-30-2026',
            default   => $format,
        };
    }
}
