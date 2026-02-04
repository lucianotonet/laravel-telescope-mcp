<?php

namespace Tests\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Helpers para criar entradas do Telescope em testes.
 */
class TelescopeTestHelpers
{
    /**
     * Retorna o nome da conexão do Telescope.
     */
    public static function telescopeConnection(): string
    {
        return config('telescope.storage.database.connection', config('database.default'));
    }

    /**
     * Cria uma entrada no telescope_entries.
     *
     * @param  array{type?: string, batch_id?: string, content?: array, uuid?: string, created_at?: string}  $overrides
     * @return string UUID da entrada criada
     */
    public static function createTelescopeEntry(array $overrides = []): string
    {
        $uuid = $overrides['uuid'] ?? (string) Str::uuid();
        $batchId = $overrides['batch_id'] ?? (string) Str::uuid();
        $type = $overrides['type'] ?? 'request';
        $content = $overrides['content'] ?? [];
        $createdAt = $overrides['created_at'] ?? now()->format('Y-m-d H:i:s');

        DB::connection(self::telescopeConnection())->table('telescope_entries')->insert([
            'uuid' => $uuid,
            'batch_id' => $batchId,
            'family_hash' => $overrides['family_hash'] ?? null,
            'should_display_on_index' => $overrides['should_display_on_index'] ?? true,
            'type' => $type,
            'content' => json_encode($content),
            'created_at' => $createdAt,
        ]);

        return $uuid;
    }

    /**
     * Cria uma entrada do tipo request.
     *
     * @param  array{method?: string, uri?: string, response_status?: int, duration?: float}  $content
     */
    public static function createRequestEntry(string $batchId = null, array $content = []): string
    {
        $batchId = $batchId ?? (string) Str::uuid();
        $defaults = [
            'method' => 'GET',
            'uri' => '/test',
            'response_status' => 200,
            'duration' => 10.5,
            'created_at' => now()->toIso8601String(),
        ];

        return self::createTelescopeEntry([
            'type' => 'request',
            'batch_id' => $batchId,
            'content' => array_merge($defaults, $content),
        ]);
    }

    /**
     * Cria uma entrada do tipo log.
     *
     * @param  array{level?: string, message?: string, context?: array}  $content
     */
    public static function createLogEntry(string $batchId = null, array $content = []): string
    {
        $batchId = $batchId ?? (string) Str::uuid();
        $defaults = [
            'level' => 'info',
            'message' => 'Test log message',
            'context' => [],
        ];

        return self::createTelescopeEntry([
            'type' => 'log',
            'batch_id' => $batchId,
            'content' => array_merge($defaults, $content),
        ]);
    }

    /**
     * Cria uma entrada do tipo exception.
     */
    public static function createExceptionEntry(string $batchId = null, array $content = []): string
    {
        $batchId = $batchId ?? (string) Str::uuid();
        $defaults = [
            'class' => \Exception::class,
            'message' => 'Test exception',
            'file' => '/app/Test.php',
            'line' => 42,
            'trace' => [],
        ];

        return self::createTelescopeEntry([
            'type' => 'exception',
            'batch_id' => $batchId,
            'content' => array_merge($defaults, $content),
        ]);
    }

    /**
     * Cria uma entrada do tipo query.
     */
    public static function createQueryEntry(string $batchId = null, array $content = []): string
    {
        $batchId = $batchId ?? (string) Str::uuid();
        $defaults = [
            'sql' => 'select * from users',
            'time' => 5.2,
            'connection' => 'testbench',
            'created_at' => now()->toIso8601String(),
        ];

        return self::createTelescopeEntry([
            'type' => 'query',
            'batch_id' => $batchId,
            'content' => array_merge($defaults, $content),
        ]);
    }

    /**
     * Cria uma entrada do tipo cache.
     */
    public static function createCacheEntry(string $batchId = null, array $content = []): string
    {
        $batchId = $batchId ?? (string) Str::uuid();
        $defaults = [
            'type' => 'hit',
            'key' => 'test-key',
            'duration' => 0.1,
        ];

        return self::createTelescopeEntry([
            'type' => 'cache',
            'batch_id' => $batchId,
            'content' => array_merge($defaults, $content),
        ]);
    }

    /**
     * Cria uma entrada do tipo job.
     */
    public static function createJobEntry(string $batchId = null, array $content = []): string
    {
        $batchId = $batchId ?? (string) Str::uuid();
        $defaults = [
            'name' => 'App\Jobs\TestJob',
            'status' => 'processed',
            'queue' => 'default',
            'attempts' => 1,
        ];

        return self::createTelescopeEntry([
            'type' => 'job',
            'batch_id' => $batchId,
            'content' => array_merge($defaults, $content),
        ]);
    }

    /**
     * Adiciona uma tag a uma entrada.
     */
    public static function addTagToEntry(string $entryUuid, string $tag): void
    {
        DB::connection(self::telescopeConnection())->table('telescope_entries_tags')->insert([
            'entry_uuid' => $entryUuid,
            'tag' => $tag,
        ]);
    }

    /**
     * Cria um batch com uma request e entradas relacionadas (logs, queries, etc.).
     *
     * @return array{request_id: string, batch_id: string}
     */
    public static function createBatchWithRequest(array $requestContent = []): array
    {
        $batchId = (string) Str::uuid();
        $requestId = self::createRequestEntry($batchId, $requestContent);
        self::createLogEntry($batchId, ['message' => 'Log during request']);
        self::createQueryEntry($batchId, ['sql' => 'select 1']);

        return ['request_id' => $requestId, 'batch_id' => $batchId];
    }
}
