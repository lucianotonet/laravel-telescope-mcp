<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;

/**
 * Abstract base class for all MCP tools
 */
abstract class AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;
    
    /**
     * @var string
     */
    protected $prefix = '';
    
    /**
     * AbstractTool constructor
     * 
     * @param EntriesRepository|null $entriesRepository The Telescope entries repository
     */
    public function __construct(EntriesRepository $entriesRepository = null)
    {
        $this->entriesRepository = $entriesRepository;
    }
    
    /**
     * Returns the tool's name
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->getShortName();
    }
    
    /**
     * Returns the tool's short name (without prefix)
     * 
     * @return string
     */
    abstract public function getShortName(): string;
    
    /**
     * Returns the tool's schema
     * 
     * @return array
     */
    abstract public function getSchema(): array;
    
    /**
     * Executes the tool with the given parameters
     * 
     * @param array $params Tool parameters
     * @return array Response in MCP format
     */
    abstract public function execute(array $params): array;
    
    /**
     * Checks if an ID was provided in the parameters
     * 
     * @param array $params Tool parameters
     * @return bool
     */
    protected function hasId(array $params): bool
    {
        return isset($params['id']) && !empty($params['id']);
    }
    
    /**
     * Gets details of a specific Telescope entry
     * 
     * @param string $entryType The type of entry (e.g., cache, request, log)
     * @param string $id The entry ID
     * @return mixed The entry details
     * @throws \Exception When entry is not found
     */
    protected function getEntryDetails(string $entryType, string $id)
    {
        Logger::debug("Getting details for {$entryType} entry", ['id' => $id]);
        
        try {
            return $this->entriesRepository->find($id);
        } catch (\Exception $e) {
            Logger::error("Failed to get entry details", [
                'id' => $id,
                'entryType' => $entryType,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception("Entry not found: {$id}");
        }
    }
    
    /**
     * Formats a response for MCP
     * 
     * @param mixed $data The data to format
     * @param string $type The response type (default: text)
     * @return array Response in MCP format
     */
    protected function formatResponse($data, string $type = 'text'): array
    {
        // Convert to string if needed
        $text = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT);
        
        // Return in strict MCP format
        return [
            'content' => [
                [
                    'type' => $type,
                    'text' => $text
                ]
            ]
        ];
    }
    
    /**
     * Formats an error response for MCP
     * 
     * @param string $message The error message
     * @return array Response in MCP format
     */
    protected function formatError(string $message): array
    {
        return $this->formatResponse("Error: " . $message, 'text');
    }
    
    /**
     * Safely converts a value to string for strlen() operations
     * 
     * @param mixed $value The value to convert
     * @return string The string representation
     */
    protected function safeString($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        } elseif (is_object($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        } elseif (is_null($value)) {
            return '';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } else {
            return (string) $value;
        }
    }
    
    /**
     * Creates a structured response with multiple content types
     * 
     * @param array $contentItems Array of content items with 'type' and 'text' keys
     * @return array Response in MCP format
     */
    protected function formatStructuredResponse(array $contentItems): array
    {
        $validatedContent = [];
        
        foreach ($contentItems as $item) {
            if (is_array($item) && isset($item['type']) && isset($item['text'])) {
                $validatedContent[] = [
                    'type' => (string) $item['type'],
                    'text' => (string) $item['text']
                ];
            }
        }
        
        if (empty($validatedContent)) {
            $validatedContent = [
                [
                    'type' => 'text',
                    'text' => 'No content available'
                ]
            ];
        }
        
        return ['content' => $validatedContent];
    }
    

} 