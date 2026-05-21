<?php

namespace Modules\KanbanWorkflow\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\KanbanWorkflow\Services\KanbanService;

define('KANBAN_WORKFLOW_MODULE', 'kanbanworkflow');

class KanbanWorkflowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'kanbanworkflow');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'kanbanworkflow');
        $this->registerHooks();
    }

    public function register(): void
    {
        $this->app->singleton(KanbanService::class);
    }

    protected function registerHooks(): void
    {
        if (!\Module::isActive('workflows') || !\Module::isActive('kanban')) {
            return;
        }

        \Eventy::addFilter('workflows.actions_config', function (array $config, $mailbox_id): array {
            $placements = app(KanbanService::class)->getPlacementOptionsForMailbox((int) $mailbox_id);

            $config['kanban'] = [
                'title' => __('kanbanworkflow::messages.action_group_kanban'),
                'items' => [
                    'add_to_kanban' => [
                        'title'  => __('kanbanworkflow::messages.action_add_to_kanban'),
                        'values' => $placements,
                    ],
                ],
            ];

            return $config;
        }, 20, 2);

        \Eventy::addFilter('workflow.perform_action', function ($performed, $type, $operator, $value, $conversation, $workflow) {
            if ($type !== 'add_to_kanban') {
                return $performed;
            }

            $placement = app(KanbanService::class)->parseActionValue($value);
            if (!$placement) {
                return $performed;
            }

            if (!app(KanbanService::class)->addConversationToBoard(
                $conversation,
                $placement['board_id'],
                $placement['column_id'],
                $placement['swimlane_id']
            )) {
                return $performed;
            }

            return true;
        }, 20, 6);

        \Eventy::addFilter('workflow.validate_action', function ($has_error, $action, $workflow) {
            if (($action['type'] ?? '') !== 'add_to_kanban') {
                return $has_error;
            }

            $placement = app(KanbanService::class)->parseActionValue($action['value'] ?? '');
            if (!$placement) {
                return true;
            }

            if (!app(KanbanService::class)->placementIsValid(
                $placement['board_id'],
                $placement['column_id'],
                $placement['swimlane_id'],
                (int) $workflow->mailbox_id
            )) {
                return true;
            }

            return $has_error;
        }, 20, 3);
    }
}
