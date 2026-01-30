<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use LucianoTonet\TelescopeMcp\Support\Logger;
use Illuminate\Support\Facades\Artisan;
use Laravel\Telescope\Contracts\EntriesRepository;

/**
 * Tool for pruning old Telescope entries
 */
class PruneTool extends AbstractTool
{
    /**
     * Returns the tool's short name
     * 
     * @return string
     */
    public function getShortName(): string
    {
        return 'prune';
    }

    /**
     * Returns the tool's schema
     * 
     * @return array
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Prunes old Telescope entries from the database.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'hours' => [
                        'type' => 'integer',
                        'description' => 'Number of hours to keep (entries older than this will be deleted)',
                        'default' => 24
                    ]
                ],
                'required' => []
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'content' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => ['type' => 'string'],
                                'text' => ['type' => 'string']
                            ],
                            'required' => ['type', 'text']
                        ]
                    ]
                ],
                'required' => ['content']
            ],
            'examples' => [
                [
                    'description' => 'Prune entries older than 24 hours',
                    'params' => ['hours' => 24]
                ],
                [
                    'description' => 'Prune entries older than 1 week',
                    'params' => ['hours' => 168]
                ]
            ]
        ];
    }

    /**
     * Executes the tool with the given parameters
     * 
     * @param array $params Tool parameters
     * @return array Response in MCP format
     */
    public function execute(array $params): array
    {
        Logger::info($this->getName() . ' execute method called', ['params' => $params]);

        try {
            // Get hours parameter with default value
            $hours = isset($params['hours']) ? (int)$params['hours'] : 24;

            // Execute the prune command
            $exitCode = Artisan::call('telescope:prune', ['--hours' => $hours]);
            $output = Artisan::output();

            if ($exitCode !== 0) {
                throw new \Exception('Failed to execute prune command. Exit code: ' . $exitCode);
            }

            return $this->formatResponse($output ?: 'Pruned entries successfully.');
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }
} 