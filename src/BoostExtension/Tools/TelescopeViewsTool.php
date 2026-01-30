<?php

namespace LucianoTonet\TelescopeMcp\BoostExtension\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LucianoTonet\TelescopeMcp\BoostExtension\TelescopeBoostTool;

class TelescopeViewsTool extends TelescopeBoostTool
{
    protected string $name = 'telescope_views';

    public function description(): string
    {
        return 'Access Views data from Laravel Telescope';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Get details of a specific entry by ID'),
            'limit' => $schema->integer()->default(50)->description('Maximum number of entries to return'),
        ];
    }
}
