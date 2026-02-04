<?php

use LucianoTonet\TelescopeMcp\Support\DateFormatter;

test('formats DateTime object with default format', function () {
    $date = new \DateTime('2025-01-15 10:30:00');

    expect(DateFormatter::format($date))->toBe('2025-01-15 10:30:00');
});

test('formats DateTime object with custom format', function () {
    $date = new \DateTime('2025-01-15 10:30:00');

    expect(DateFormatter::format($date, 'd/m/Y'))->toBe('15/01/2025');
    expect(DateFormatter::format($date, 'Y-m-d'))->toBe('2025-01-15');
});

test('formats date string with default format', function () {
    expect(DateFormatter::format('2025-01-15 10:30:00'))->toBe('2025-01-15 10:30:00');
    expect(DateFormatter::format('2025-06-20 08:00:00'))->toBe('2025-06-20 08:00:00');
});

test('formats date string with custom format', function () {
    expect(DateFormatter::format('2025-01-15 10:30:00', 'd/m/Y H:i'))->toBe('15/01/2025 10:30');
});

test('returns default for null', function () {
    expect(DateFormatter::format(null))->toBe('Unknown');
});

test('returns default for empty string', function () {
    expect(DateFormatter::format(''))->toBe('Unknown');
});

test('returns custom default when provided', function () {
    expect(DateFormatter::format(null, 'Y-m-d', 'N/A'))->toBe('N/A');
    expect(DateFormatter::format('', 'Y-m-d', '-'))->toBe('-');
});

test('returns default for invalid date string', function () {
    expect(DateFormatter::format('not-a-date'))->toBe('Unknown');
});

test('returns default for whitespace-only string', function () {
    expect(DateFormatter::format('   '))->toBe('Unknown');
});

test('formats DateTimeImmutable object', function () {
    $date = new \DateTimeImmutable('2025-03-10 12:00:00');

    expect(DateFormatter::format($date))->toBe('2025-03-10 12:00:00');
});
