<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

class MailTool extends AbstractTool
{
    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName(): string
    {
        return 'mail';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa e-mails enviados registrados pelo Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID do e-mail específico para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de e-mails a retornar',
                        'default' => 50
                    ],
                    'to' => [
                        'type' => 'string',
                        'description' => 'Filtrar por destinatário'
                    ],
                    'subject' => [
                        'type' => 'string',
                        'description' => 'Filtrar por assunto'
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
            ]
        ];
    }

    /**
     * Executa a ferramenta com os parâmetros fornecidos
     */
    public function execute(array $params): array
    {
        try {
            Logger::info($this->getName() . ' execute method called', ['params' => $params]);

            // Verificar se foi solicitado detalhes de um e-mail específico
            if ($this->hasId($params)) {
                return $this->getMailDetails($params['id']);
            }
            
            return $this->listMails($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista os e-mails registrados pelo Telescope
     */
    protected function listMails(array $params): array
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;
        
        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);
        
        // Adicionar filtros se especificados
        if (!empty($params['to'])) {
            $options->tag($params['to']);
        }
        if (!empty($params['subject'])) {
            $options->tag($params['subject']);
        }
        
        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::MAIL, $options);
        
        if (empty($entries)) {
            return $this->formatResponse("Nenhum e-mail encontrado.");
        }
        
        $mails = [];
        
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            
            // Get timestamp from content
            $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';
            
            // Format recipients
            $to = [];
            if (isset($content['to']) && is_array($content['to'])) {
                foreach ($content['to'] as $recipient) {
                    if (is_array($recipient)) {
                        $to[] = $recipient['address'] ?? '';
                    } else {
                        $to[] = $recipient;
                    }
                }
            }
            
            $mails[] = [
                'id' => $entry->id,
                'subject' => $content['subject'] ?? 'No Subject',
                'to' => implode(', ', $to),
                'created_at' => $createdAt
            ];
        }
        
        // Formatação tabular para facilitar a leitura
        $table = "E-mails:\n\n";
        $table .= sprintf("%-5s %-40s %-40s %-20s\n", "ID", "Subject", "To", "Created At");
        $table .= str_repeat("-", 110) . "\n";
        
        foreach ($mails as $mail) {
            // Truncar assunto e destinatários se muito longos
            $subject = $mail['subject'];
            if (strlen($subject) > 40) {
                $subject = substr($subject, 0, 37) . "...";
            }
            
            $to = $mail['to'];
            if (strlen($to) > 40) {
                $to = substr($to, 0, 37) . "...";
            }
            
            $table .= sprintf(
                "%-5s %-40s %-40s %-20s\n",
                $mail['id'],
                $subject,
                $to,
                $mail['created_at']
            );
        }
        
        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de um e-mail específico
     */
    protected function getMailDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);
        
        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::MAIL, $id);
        
        if (!$entry) {
            return $this->formatError("E-mail não encontrado: {$id}");
        }
        
        $content = is_array($entry->content) ? $entry->content : [];
        
        // Get timestamp from content
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';
        
        // Formatação detalhada do e-mail
        $output = "E-mail Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Subject: " . ($content['subject'] ?? 'No Subject') . "\n";
        
        // Destinatários
        if (isset($content['to']) && is_array($content['to'])) {
            $output .= "To:\n";
            foreach ($content['to'] as $recipient) {
                if (is_array($recipient)) {
                    $output .= "- " . ($recipient['address'] ?? '') . 
                             (isset($recipient['name']) ? " ({$recipient['name']})" : '') . "\n";
                } else {
                    $output .= "- " . $recipient . "\n";
                }
            }
        }
        
        // CC
        if (isset($content['cc']) && !empty($content['cc'])) {
            $output .= "\nCC:\n";
            foreach ($content['cc'] as $recipient) {
                if (is_array($recipient)) {
                    $output .= "- " . ($recipient['address'] ?? '') . 
                             (isset($recipient['name']) ? " ({$recipient['name']})" : '') . "\n";
                } else {
                    $output .= "- " . $recipient . "\n";
                }
            }
        }
        
        // BCC
        if (isset($content['bcc']) && !empty($content['bcc'])) {
            $output .= "\nBCC:\n";
            foreach ($content['bcc'] as $recipient) {
                if (is_array($recipient)) {
                    $output .= "- " . ($recipient['address'] ?? '') . 
                             (isset($recipient['name']) ? " ({$recipient['name']})" : '') . "\n";
                } else {
                    $output .= "- " . $recipient . "\n";
                }
            }
        }
        
        $output .= "Created At: {$createdAt}\n\n";
        
        // Email content
        if (isset($content['html'])) {
            $output .= "HTML Content:\n" . $content['html'] . "\n\n";
        }
        if (isset($content['text'])) {
            $output .= "Text Content:\n" . $content['text'] . "\n";
        }
        
        // Anexos
        if (isset($content['attachments']) && !empty($content['attachments'])) {
            $output .= "\nAttachments:\n";
            foreach ($content['attachments'] as $attachment) {
                $output .= "- " . ($attachment['file'] ?? 'Unknown file') . "\n";
            }
        }
        
        return $this->formatResponse($output);
    }
} 