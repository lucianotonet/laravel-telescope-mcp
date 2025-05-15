<?php

namespace Tests\Integration;

use Carbon\Carbon;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use PHPUnit\Framework\TestCase;

class DateFormatterTest extends TestCase
{
    /** @test */
    public function it_formats_carbon_dates()
    {
        $date = Carbon::create(2024, 3, 14, 10, 30, 45);
        $formatted = DateFormatter::format($date);
        
        $this->assertEquals('2024-03-14 10:30:45', $formatted);
    }

    /** @test */
    public function it_formats_datetime_objects()
    {
        $date = new \DateTime('2024-03-14 10:30:45');
        $formatted = DateFormatter::format($date);
        
        $this->assertEquals('2024-03-14 10:30:45', $formatted);
    }

    /** @test */
    public function it_formats_string_dates()
    {
        $date = '2024-03-14 10:30:45';
        $formatted = DateFormatter::format($date);
        
        $this->assertEquals('2024-03-14 10:30:45', $formatted);
    }

    /** @test */
    public function it_handles_invalid_dates()
    {
        $formatted = DateFormatter::format('invalid-date');
        
        $this->assertEquals('Unknown', $formatted);
    }

    /** @test */
    public function it_handles_null_values()
    {
        $formatted = DateFormatter::format(null);
        
        $this->assertEquals('Unknown', $formatted);
    }
} 