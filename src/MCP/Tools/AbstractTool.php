<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;

/**
 * Classe base abstrata para todas as ferramentas do MCP
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
     * @param EntriesRepository $entriesRepository
     */
    public function __construct(EntriesRepository $entriesRepository = null)
    {
        $this->entriesRepository = $entriesRepository;
    }
    
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
     * Retorna o nome curto da ferramenta (sem o prefixo)
     * 
     * @return string
     */
    abstract public function getShortName();
    
    /**
     * Retorna o esquema da ferramenta
     * 
     * @return array
     */
    abstract public function getSchema();
    
    /**
     * Executa a ferramenta com os parâmetros fornecidos
     * 
     * @param array $params
     * @return array
     */
    abstract public function execute($params);
    
    /**
     * Verifica se um ID foi fornecido nos parâmetros
     * 
     * @param array $params
     * @return bool
     */
    protected function hasId($params)
    {
        return isset($params['id']) && !empty($params['id']);
    }
    
    /**
     * Obtém os detalhes de uma entrada específica do Telescope
     * 
     * @param string $entryType
     * @param string $id
     * @return mixed
     */
    protected function getEntryDetails($entryType, $id)
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
     * Formata uma resposta para o MCP
     * 
     * @param mixed $data
     * @param string $type
     * @return array
     */
    protected function formatResponse($data, $type = 'text')
    {
        // Se já for um array formatado com 'content', retorne-o diretamente
        if (is_array($data) && isset($data['content'])) {
            return $data;
        }
        
        // Converte para string se necessário
        $text = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT);
        
        // Retorna no formato esperado pelo MCP
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
     * Formata uma resposta de erro para o MCP
     * 
     * @param string $message
     * @return array
     */
    protected function formatError($message)
    {
        return $this->formatResponse($message, 'error');
    }
} 