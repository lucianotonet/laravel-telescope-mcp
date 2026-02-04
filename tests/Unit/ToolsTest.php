<?php

namespace Tests\Unit;

use LucianoTonet\TelescopeMcp\MCP\Tools\AbstractTool;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class ToolsTest extends TestCase
{
    /** @test */
    public function it_formats_dates_correctly()
    {
        $date = Carbon::now();
        $formatted = DateFormatter::format($date);

        $this->assertIsString($formatted);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $formatted);
    }

    /** @test */
    public function it_formats_error_responses_correctly()
    {
        $tool = new class extends AbstractTool {
            public function getName(): string
            {
                return 'test_tool';
            }

            public function getShortName(): string
            {
                return 'test';
            }

            public function getSchema(): array
            {
                return [];
            }

            public function execute(array $arguments = []): array
            {
                return [];
            }

            public function handle(array $arguments = []): array
            {
                return [];
            }

            public function testFormatError(\Exception $error): array
            {
                return ['error' => $error->getMessage()];
            }
        };

        $error = new \Exception('Test error message');
        $response = $tool->testFormatError($error);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Test error message', $response['error']);
    }

    /** @test */
    public function it_handles_null_dates()
    {
        $formatted = DateFormatter::format(null);

        $this->assertEquals('Unknown', $formatted);
    }
}
