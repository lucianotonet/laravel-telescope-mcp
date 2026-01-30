<?php

use Carbon\Carbon;
use LucianoTonet\TelescopeMcp\MCP\Tools\AbstractTool;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

test('it formats dates correctly', function () {
    $date = Carbon::now();
    $formatted = DateFormatter::format($date);

    expect($formatted)
        ->toBeString()
        ->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

test('it handles null dates', function () {
    $formatted = DateFormatter::format(null);

    expect($formatted)->toBe('Unknown');
});

test('abstract tool can format responses correctly', function () {
    $tool = new class extends AbstractTool {
        public function getShortName(): string
        {
            return 'test_tool';
        }

        public function getSchema(): array
        {
            return [
                'name' => 'test_tool',
                'description' => 'A test tool',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ];
        }

        public function execute(array $params): array
        {
            return $this->formatResponse(['test' => 'data']);
        }

        public function testFormatResponse($data): array
        {
            return $this->formatResponse($data);
        }

        public function testFormatError(string $message): array
        {
            return $this->formatError($message);
        }
    };

    $response = $tool->testFormatResponse(['key' => 'value']);

    expect($response)
        ->toBeArray()
        ->toHaveKey('content')
        ->and($response['content'])->toBeArray()
        ->and($response['content'][0])->toHaveKeys(['type', 'text'])
        ->and($response['content'][0]['type'])->toBe('text');
});

test('abstract tool can format error responses', function () {
    $tool = new class extends AbstractTool {
        public function getShortName(): string
        {
            return 'test_tool';
        }

        public function getSchema(): array
        {
            return [];
        }

        public function execute(array $params): array
        {
            return [];
        }

        public function testFormatError(string $message): array
        {
            return $this->formatError($message);
        }
    };

    $response = $tool->testFormatError('Test error message');

    expect($response)
        ->toBeArray()
        ->toHaveKey('content')
        ->and($response['content'][0]['text'])->toContain('Error: Test error message');
});

test('abstract tool getName returns short name', function () {
    $tool = new class extends AbstractTool {
        public function getShortName(): string
        {
            return 'my_tool';
        }

        public function getSchema(): array
        {
            return [];
        }

        public function execute(array $params): array
        {
            return [];
        }
    };

    expect($tool->getName())->toBe('my_tool');
});

test('abstract tool safeString converts various types', function () {
    $tool = new class extends AbstractTool {
        public function getShortName(): string
        {
            return 'test';
        }

        public function getSchema(): array
        {
            return [];
        }

        public function execute(array $params): array
        {
            return [];
        }

        public function testSafeString($value): string
        {
            return $this->safeString($value);
        }
    };

    expect($tool->testSafeString(null))->toBe('')
        ->and($tool->testSafeString(true))->toBe('true')
        ->and($tool->testSafeString(false))->toBe('false')
        ->and($tool->testSafeString(123))->toBe('123')
        ->and($tool->testSafeString('hello'))->toBe('hello')
        ->and($tool->testSafeString(['a' => 1]))->toBeJson();
});
