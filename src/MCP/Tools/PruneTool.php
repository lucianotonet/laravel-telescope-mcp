<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use LucianoTonet\TelescopeMcp\Support\Logger;
use Illuminate\Support\Facades\Artisan;
use Laravel\Telescope\Contracts\EntriesRepository;

class PruneTool extends AbstractTool
{
    /**
     * Retorna o nome da ferramenta
     *
     * @return string
     */
    public function getName()
    {
        return $this->getShortName();
    }

    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName()
    {
        return 'prune';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Clears old Telescope entries. Similar to `php artisan telescope:prune`.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'hours' => [
                        'type' => 'integer',
                        'description' => 'The number of hours of entries to retain. Older entries will be pruned. If not specified (or 0), all entries may be pruned depending on underlying command behavior.',
                        'default' => null
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
                    'description' => 'Prune entries older than 48 hours.',
                    'params' => ['hours' => 48]
                ],
                [
                    'description' => 'Prune all entries (behavior might depend on underlying command default if hours is 0 or not provided). Check Telescope documentation for `telescope:prune`.',
                    'params' => ['hours' => 0]
                ]
            ]
        ];
    }

    public function execute($params)
    {
        Logger::info($this->getName() . ' execute method called', ['params' => $params]);

        $hours = $params['hours'] ?? null;
        $artisanParams = [];

        if ($hours !== null && is_int($hours) && $hours > 0) {
            $artisanParams['--hours'] = $hours;
            Logger::info('Pruning Telescope entries', ['hours' => $hours]);
            $message = "Attempting to prune Telescope entries older than {$hours} hours...";
        } else {
            // If hours is null, 0 or not an int, it implies pruning all entries (or default behavior)
            Logger::info('Pruning all Telescope entries (or default behavior)');
            $message = "Attempting to prune all Telescope entries (or using default behavior)...";
        }

        // Actual Artisan call would be:
        try {
            Artisan::call('telescope:prune', $artisanParams);
            $output = Artisan::output();
            Logger::info('telescope:prune command output', ['output' => $output]);
            $resultText = $output ?: 'Telescope entries pruned successfully.';
            return $this->formatResponse($resultText);
        } catch (\Exception $e) {
            Logger::error('Error executing telescope:prune', ['error' => $e->getMessage()]);
            $errorText = 'Error executing telescope:prune: ' . $e->getMessage();
            return [
                'content' => [
                    [
                        'type' => 'error',
                        'text' => $errorText
                    ]
                ]
            ];
        }
    }
} 