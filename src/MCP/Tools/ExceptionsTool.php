<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;

class ExceptionsTool
{
    protected $entriesRepository;

    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
    }

    public function getName()
    {
        return 'mcp_telescope_exceptions';
    }

    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'MCP Telescope Exceptions Tool - Em desenvolvimento. Esta ferramenta irá interagir com as exceções registradas pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object) [
                    // Specific parameters for this tool will be defined in the future
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
                // Usage examples will be added in the future
            ]
        ];
    }

    public function execute($params)
    {
        Logger::info($this->getName() . ' execute method called', ['params' => $params]);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Exceptions Tool is under development. Functionality to retrieve Telescope exception entries will be implemented here.'
                ]
            ]
        ];
    }
} 