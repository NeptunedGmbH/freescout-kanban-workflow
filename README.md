# FreeScout — Kanban Workflow

A custom module for [FreeScout](https://freescout.net) that adds an **Add to Kanban Board** action to the **Workflows** module. Use it to automatically place new (or matching) conversations onto a Kanban board when workflow conditions are met.

---

## Requirements

- FreeScout (App version ≥ 1.8.117)
- [Kanban module](https://freescout.net/module/kanban/) (official, paid) ≥ 1.0.0
- [Workflows module](https://freescout.net/module/workflows/) (official, paid) ≥ 1.0.0

---

## Installation

### Via Docker

```bash
docker cp ./KanbanWorkflow freescout-app:/www/html/Modules/KanbanWorkflow

docker exec freescout-app php /www/html/artisan cache:clear
docker exec freescout-app php /www/html/artisan config:clear
```

### Manual

Copy this folder into your FreeScout `/Modules/` directory as `KanbanWorkflow`, then clear the application cache.

After installation, go to **Manage → Modules** and activate **Kanban Workflow**.

---

## Configuration

Edit `Config/config.php` or set environment variables in `.env`:

| Setting | Env variable | Default | Description |
|---|---|---|---|
| `skip_if_already_on_board` | `KANBAN_WORKFLOW_SKIP_IF_ALREADY_ON_BOARD` | `true` | Skip creating a duplicate card when the conversation is already on the selected board |

Example `.env` entry:

```env
KANBAN_WORKFLOW_SKIP_IF_ALREADY_ON_BOARD=true
```

---

## Usage

### Example: Add new customer emails to a board

1. Open **Mailbox → Workflows** and create an **Automatic** workflow.
2. Add a condition such as **New / Reply / Moved → New conversation** (or any conditions you need).
3. Add the action **Kanban → Add to Kanban Board (Board → Column → Swimlane)** and pick the target placement from the dropdown (e.g. `Support → Eingang → Standard`).
4. Save and activate the workflow.

When the workflow runs, the conversation is linked to the selected Kanban board as a card in the chosen column and swimlane (same as using **Add to Board** on the conversation page).

### Tips

- Placements listed in the action dropdown belong to the workflow’s mailbox and include board, column, and swimlane.
- Combine with tags, subject filters, or custom-field conditions for routing rules.
- Pair with the [Kanban Auto Refresh](https://github.com/NeptunedGmbH/freescout-kanban-auto-refresh) module so open board views update automatically.

---

## How It Works

The module hooks into three Eventy filters exposed by the Workflows module:

| Hook | Purpose |
|---|---|
| `workflows.actions_config` | Registers **Add to Kanban Board** in the Workflow Builder (Kanban group) |
| `workflow.perform_action` | Creates the Kanban card when the workflow runs |
| `workflow.validate_action` | Ensures a valid board is selected before saving the workflow |

Card creation uses the official Kanban module’s `KnBoard` and `KnCard` entities (same code path as **Add to Board** on a conversation). Column and swimlane come from the workflow action selection. Other modules can override behavior via the `kanbanworkflow.add_conversation` filter.

---

## Troubleshooting

**Action does not appear in Workflows**

Confirm that **Kanban**, **Workflows**, and **Kanban Workflow** are all active, then clear the config cache.

**Workflow saves but cards are not created**

Check **Manage → Logs → App Logs** for `kanbanworkflow` entries. Verify the selected board exists and belongs to the workflow mailbox.

**Duplicate cards**

Set `KANBAN_WORKFLOW_SKIP_IF_ALREADY_ON_BOARD=true` (default) to skip conversations already on the board.

---

## License

MIT
