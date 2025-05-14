<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class NotificationsTool extends AbstractTool
{
    protected $entriesRepository;

    public function __construct(EntriesRepository $entriesRepository)
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
     * Retorna o nome curto da ferramenta
     */
    public function getShortName()
    {
        return 'notifications';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa notificações enviadas e registradas pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da notificação específica para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de notificações a retornar',
                        'default' => 50
                    ],
                    'type' => [
                        'type' => 'string',
                        'description' => 'Filtrar por tipo de notificação (mail, database, broadcast, etc.)'
                    ],
                    'notifiable' => [
                        'type' => 'string',
                        'description' => 'Filtrar por destinatário da notificação (classe ou identificador)'
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
                // Usage examples will be added in the future
            ]
        ];
    }

    public function execute($params)
    {
        Logger::info($this->getName() . ' execute method called', ['params' => $params]);

        try {
            // Verificar se foi solicitado detalhes de uma notificação específica
            if ($this->hasId($params)) {
                return $this->getNotificationDetails($params['id']);
            }

            return $this->listNotifications($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista as notificações registradas pelo Telescope
     */
    protected function listNotifications($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Adicionar filtros se especificados
        if (!empty($params['type'])) {
            $options->tag($params['type']);
        }
        if (!empty($params['notifiable'])) {
            $options->tag($params['notifiable']);
        }

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::NOTIFICATION, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhuma notificação encontrada.");
        }

        $notifications = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];

            $createdAt = 'Unknown';
            if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
                if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                    $createdAt = $entry->created_at->format('Y-m-d H:i:s');
                } elseif (is_string($entry->created_at)) {
                    $createdAt = $entry->created_at;
                }
            }

            $notifications[] = [
                'id' => $entry->id,
                'type' => $content['type'] ?? 'Unknown',
                'notifiable' => $content['notifiable'] ?? 'Unknown',
                'notification' => $content['notification'] ?? 'Unknown', // Classe da notificação
                'channel' => $content['channel'] ?? 'Unknown', // Canal (mail, database, etc.)
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Notifications:\n\n";
        $table .= sprintf("%-5s %-20s %-30s %-30s %-15s %-20s\n", "ID", "Type", "Notifiable", "Notification", "Channel", "Created At");
        $table .= str_repeat("-", 130) . "\n";

        foreach ($notifications as $notification) {
             // Truncar campos longos
            $notifiable = $notification['notifiable'];
            if (strlen($notifiable) > 30) {
                $notifiable = substr($notifiable, 0, 27) . "...";
            }

            $notificationClass = $notification['notification'];
            if (strlen($notificationClass) > 30) {
                $notificationClass = substr($notificationClass, 0, 27) . "...";
            }

            $table .= sprintf(
                "%-5s %-20s %-30s %-30s %-15s %-20s\n",
                $notification['id'],
                $notification['type'],
                $notifiable,
                $notificationClass,
                $notification['channel'],
                $notification['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de uma notificação específica
     */
    protected function getNotificationDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::NOTIFICATION, $id);

        if (!$entry) {
            return $this->formatError("Notificação não encontrada: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Formatação detalhada da notificação
        $output = "Notification Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Type: " . ($content['type'] ?? 'Unknown') . "\n";
        $output .= "Notification Class: " . ($content['notification'] ?? 'Unknown') . "\n";
        $output .= "Channel: " . ($content['channel'] ?? 'Unknown') . "\n";
        $output .= "Notifiable: " . ($content['notifiable'] ?? 'Unknown') . "\n";

        $createdAt = 'Unknown';
        if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
            if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                $createdAt = $entry->created_at->format('Y-m-d H:i:s');
            } elseif (is_string($entry->created_at)) {
                $createdAt = $entry->created_at;
            }
        }
        $output .= "Created At: {$createdAt}\n\n";

        // Dados da notificação
        if (isset($content['data']) && !empty($content['data'])) {
            $output .= "Data:\n" . json_encode($content['data'], JSON_PRETTY_PRINT) . "\n";
        }

        // TODO: Adicionar outros detalhes relevantes se existirem

        return $this->formatResponse($output);
    }
} 