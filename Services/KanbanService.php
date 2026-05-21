<?php

namespace Modules\KanbanWorkflow\Services;

use App\Conversation;
use Modules\Kanban\Entities\KnBoard;
use Modules\Kanban\Entities\KnCard;

class KanbanService
{
    /**
     * Placement options for the workflow action (value => label).
     *
     * Value format: board_id:column_id:swimlane_id
     */
    public function getPlacementOptionsForMailbox(int $mailboxId): array
    {
        if (!\Module::isActive('kanban')) {
            return [];
        }

        $options = [];

        $boards = KnBoard::where('mailbox_id', $mailboxId)->orderBy('name')->get();
        foreach ($boards as $board) {
            $columns = $this->getBoardItemNames($board->columns ?? []);
            $swimlanes = $this->getBoardItemNames($board->swimlanes ?? []);

            if (empty($columns) || empty($swimlanes)) {
                continue;
            }

            foreach ($columns as $columnId => $columnName) {
                foreach ($swimlanes as $swimlaneId => $swimlaneName) {
                    $key = $this->encodePlacement($board->id, $columnId, $swimlaneId);
                    $options[$key] = $board->name . ' → ' . $columnName . ' → ' . $swimlaneName;
                }
            }
        }

        return $options;
    }

    public function boardExists(int $boardId, int $mailboxId): bool
    {
        return KnBoard::where('id', $boardId)
            ->where('mailbox_id', $mailboxId)
            ->exists();
    }

    public function placementIsValid(int $boardId, int $columnId, int $swimlaneId, int $mailboxId): bool
    {
        $board = KnBoard::where('id', $boardId)
            ->where('mailbox_id', $mailboxId)
            ->first();

        if (!$board) {
            return false;
        }

        $columns = $this->getBoardItemNames($board->columns ?? []);
        $swimlanes = $this->getBoardItemNames($board->swimlanes ?? []);

        return isset($columns[$columnId]) && isset($swimlanes[$swimlaneId]);
    }

    /**
     * @return array{board_id: int, column_id: int, swimlane_id: int}|null
     */
    public function parseActionValue($value): ?array
    {
        if (is_array($value)) {
            $value = $value['placement'] ?? $value['value'] ?? null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $boardId = (int) $value;
            $board = KnBoard::find($boardId);
            if (!$board) {
                return null;
            }

            $columnId = $this->getDefaultColumnId($board);
            $swimlaneId = $this->getDefaultSwimlaneId($board);
            if ($columnId === null || $swimlaneId === null) {
                return null;
            }

            return [
                'board_id'     => $boardId,
                'column_id'    => $columnId,
                'swimlane_id'  => $swimlaneId,
            ];
        }

        $parts = explode(':', (string) $value);
        if (count($parts) !== 3) {
            return null;
        }

        return [
            'board_id'     => (int) $parts[0],
            'column_id'    => (int) $parts[1],
            'swimlane_id'  => (int) $parts[2],
        ];
    }

    public function addConversationToBoard(
        Conversation $conversation,
        int $boardId,
        ?int $columnId = null,
        ?int $swimlaneId = null
    ): bool {
        if (!\Module::isActive('kanban')) {
            return false;
        }

        $override = \Eventy::filter(
            'kanbanworkflow.add_conversation',
            null,
            $conversation,
            $boardId,
            $columnId,
            $swimlaneId
        );
        if ($override !== null) {
            return (bool) $override;
        }

        if (config('kanbanworkflow.skip_if_already_on_board', true)
            && $this->conversationOnBoard($conversation->id, $boardId)
        ) {
            return true;
        }

        $board = KnBoard::find($boardId);
        if (!$board) {
            return false;
        }

        $columnId = $columnId ?? $this->getDefaultColumnId($board);
        $swimlaneId = $swimlaneId ?? $this->getDefaultSwimlaneId($board);
        if ($columnId === null || $swimlaneId === null) {
            return false;
        }

        if (!$this->placementIsValid($boardId, $columnId, $swimlaneId, (int) $board->mailbox_id)) {
            return false;
        }

        $userId = $this->workflowUserId();
        if (!$userId) {
            return false;
        }

        $card = KnCard::create([
            'name'               => $conversation->getSubject(),
            'kn_board_id'        => $board->id,
            'kn_column_id'       => $columnId,
            'kn_swimlane_id'     => $swimlaneId,
            'body'               => '',
            'created_by_user_id' => $userId,
            'imported'           => true,
            'conversation'       => $conversation,
        ], true);

        return $card !== null;
    }

    public function conversationOnBoard(int $conversationId, int $boardId): bool
    {
        return KnCard::where('kn_board_id', $boardId)
            ->where('conversation_id', $conversationId)
            ->exists();
    }

    protected function encodePlacement(int $boardId, int $columnId, int $swimlaneId): string
    {
        return $boardId . ':' . $columnId . ':' . $swimlaneId;
    }

    /**
     * @return array<int, string> id => name
     */
    protected function getBoardItemNames(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (empty($item['id'])) {
                continue;
            }
            if ((string) $item['id'] === \Kanban::PATTERN_ID) {
                continue;
            }

            $id = (int) $item['id'];
            $result[$id] = $item['name'] ?? (string) $id;
        }

        return $result;
    }

    protected function getDefaultColumnId(KnBoard $board): ?int
    {
        $columns = $this->getBoardItemNames($board->columns ?? []);

        return $columns ? (int) array_key_first($columns) : null;
    }

    protected function getDefaultSwimlaneId(KnBoard $board): ?int
    {
        $swimlanes = $this->getBoardItemNames($board->swimlanes ?? []);

        return $swimlanes ? (int) array_key_first($swimlanes) : null;
    }

    protected function workflowUserId(): ?int
    {
        if (class_exists(\Workflow::class) && method_exists(\Workflow::class, 'getUser')) {
            $user = \Workflow::getUser();

            return $user ? (int) $user->id : null;
        }

        return null;
    }
}
