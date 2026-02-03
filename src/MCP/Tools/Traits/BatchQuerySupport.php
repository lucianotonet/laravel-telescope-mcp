<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Trait providing batch query support for tools.
 *
 * Allows tools to query related entries by batch_id,
 * enabling inspection of all entries (queries, logs, cache, etc.)
 * that occurred during a specific request.
 */
trait BatchQuerySupport
{
    /**
     * Get the batch_id for a given entry.
     *
     * @param string $entryId The entry ID
     * @return string|null The batch ID or null if not found
     */
    protected function getBatchIdForEntry(string $entryId): ?string
    {
        try {
            $entry = $this->entriesRepository->find($entryId);
            return $entry->batchId ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the Telescope database connection name.
     *
     * @return string The connection name
     */
    protected function getTelescopeConnection(): string
    {
        return config('telescope.storage.database.connection', config('database.default'));
    }

    /**
     * Get entries by batch_id and type.
     *
     * @param string $batchId The batch ID
     * @param string $type The entry type (query, log, cache, etc.)
     * @param int $limit Maximum entries to return
     * @return array Array of entry objects
     */
    protected function getEntriesByBatchId(string $batchId, string $type, int $limit = 100): array
    {
        return DB::connection($this->getTelescopeConnection())
            ->table('telescope_entries')
            ->where('batch_id', $batchId)
            ->where('type', $type)
            ->orderBy('sequence', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return (object) [
                    'id' => $row->uuid,
                    'batchId' => $row->batch_id,
                    'type' => $row->type,
                    'content' => json_decode($row->content, true),
                    'createdAt' => $row->created_at,
                ];
            })
            ->all();
    }

    /**
     * Count entries by batch_id and type.
     *
     * @param string $batchId The batch ID
     * @param string $type The entry type
     * @return int The count of entries
     */
    protected function countEntriesByBatchId(string $batchId, string $type): int
    {
        return DB::connection($this->getTelescopeConnection())
            ->table('telescope_entries')
            ->where('batch_id', $batchId)
            ->where('type', $type)
            ->count();
    }

    /**
     * Get a summary of all entry types in a batch.
     *
     * @param string $batchId The batch ID
     * @return array Associative array of type => count
     */
    protected function getBatchSummary(string $batchId): array
    {
        return DB::connection($this->getTelescopeConnection())
            ->table('telescope_entries')
            ->where('batch_id', $batchId)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type')
            ->all();
    }

    /**
     * Check if request_id parameter is provided.
     *
     * @param array $params Tool parameters
     * @return bool True if request_id is provided
     */
    protected function hasRequestId(array $params): bool
    {
        return isset($params['request_id']) && !empty($params['request_id']);
    }

    /**
     * Get the batch_id for a request, with error handling.
     *
     * @param string $requestId The request ID
     * @return string|null The batch ID or null with error
     */
    protected function getBatchIdForRequest(string $requestId): ?string
    {
        $batchId = $this->getBatchIdForEntry($requestId);

        if (!$batchId) {
            return null;
        }

        return $batchId;
    }
}
