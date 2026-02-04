<?php

namespace LucianoTonet\TelescopeMcp\Support;

class DateFormatter
{
    /**
     * Format a date value to a consistent format
     *
     * @param mixed $date The date value to format (string, DateTime, or null)
     * @param string $format The desired format (default: Y-m-d H:i:s)
     * @param string $default The default value if date is invalid (default: Unknown)
     * @return string
     */
    public static function format($date, string $format = 'Y-m-d H:i:s', string $default = 'Unknown'): string
    {
        if (empty($date)) {
            return $default;
        }

        try {
            if (is_object($date) && method_exists($date, 'format')) {
                return $date->format($format);
            }

            if (is_string($date) && trim($date) !== '') {
                $dateTime = new \DateTime($date);
                return $dateTime->format($format);
            }
        } catch (\Exception $e) {
            Logger::warning('Failed to parse date', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
        }

        return $default;
    }
}
