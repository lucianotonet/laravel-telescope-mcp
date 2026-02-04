<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools\Traits;

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
            // Query directly from database since we need batch_id
            $entry = DB::connection($this->getTelescopeConnection())
                ->table('telescope_entries')
                ->where('uuid', $entryId)
                ->first();

            return $entry->batch_id ?? null;
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

    /**
     * Find the most recent request UUID for a given path.
     *
     * @param string $path The URL path
     * @return string|null The UUID or null if not found
     */
    protected function findRequestIdByPath(string $path): ?string
    {
        $uuids = $this->getRequestUuidsByPath($path, 1);
        return $uuids[0] ?? null;
    }

    /**
     * Search for request UUIDs by path.
     *
     * @param string $path The URL path
     * @param int $limit Max results
     * @return array Array of UUID strings
     */
    protected function getRequestUuidsByPath(string $path, int $limit = 50): array
    {
        try {
            // Normalize path for search
            $path = ltrim($path, '/');
            $fullPath = '/' . $path;

            // Try searching by tag first (fastest)
            $uuidsByTag = DB::connection($this->getTelescopeConnection())
                ->table('telescope_entries_tags')
                ->join('telescope_entries', 'telescope_entries.uuid', '=', 'telescope_entries_tags.entry_uuid')
                ->where('telescope_entries.type', 'request')
                ->where(function ($query) use ($path, $fullPath) {
                    $query->where('tag', 'path:' . $fullPath)
                        ->orWhere('tag', 'path:' . $path)
                        ->orWhere('tag', 'path:/' . $path);
                })
                ->orderBy('telescope_entries.sequence', 'desc')
                ->limit($limit)
                ->pluck('entry_uuid')
                ->all();

            if (count($uuidsByTag) > 0) {
                return $uuidsByTag;
            }

            // Fallback: search by content column (more reliable but slower)
            return DB::connection($this->getTelescopeConnection())
                ->table('telescope_entries')
                ->where('type', 'request')
                ->where(function ($query) use ($path) {
                    // Search for path string in various JSON formats
                    $jsonPath = str_replace('/', '\\/', $path);
                    $query->where('content', 'like', '%' . $path . '%')
                        ->orWhere('content', 'like', '%' . $jsonPath . '%');
                })
                ->orderBy('sequence', 'desc')
                ->limit($limit)
                ->pluck('uuid')
                ->all();
        } catch (\Exception $e) {
            return [];
        }
    }
}
