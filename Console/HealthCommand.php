<?php

namespace Modules\KanbanWorkflow\Console;

use Illuminate\Console\Command;

/**
 * Read-only health check for UpdateGuard.
 *
 *   php artisan kanbanworkflow:health
 *   php artisan kanbanworkflow:health --json
 *
 * Exit 0 means healthy. Exit 1 means unhealthy.
 * The command does not write, migrate, or use the network.
 */
class HealthCommand extends Command
{
    protected $signature = 'kanbanworkflow:health {--json : Print a JSON object}';

    protected $description = 'Read-only Kanban Workflow health check (UpdateGuard)';

    public function handle(): int
    {
        $root = dirname(__DIR__);
        $checks = [
            'files' => $this->files($root),
            'classes' => $this->classes(),
            'version' => $this->versionsMatch($root),
        ];
        $failed = 0;
        foreach ($checks as $result) {
            if ($result !== 'ok') {
                $failed++;
            }
        }

        $status = $failed === 0 ? 'ok' : 'fail';
        $payload = [
            'status' => $status,
            'module' => 'KanbanWorkflow',
            'version' => $this->manifestVersion($root),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                $this->line('{"status":"fail","module":"KanbanWorkflow","version":"","checks":{"json":"encode failed"}}');

                return 1;
            }
            $this->line($encoded);

            return $failed === 0 ? 0 : 1;
        }

        foreach ($checks as $name => $result) {
            $this->line(str_pad($name, 12).$result);
        }
        $this->line('KanbanWorkflow '.$payload['version'].': '.$status);

        return $failed === 0 ? 0 : 1;
    }

    protected function files(string $root): string
    {
        $relative = [
            'module.json',
            'version.txt',
            'composer.json',
            'Providers/KanbanWorkflowServiceProvider.php',
            'Services/KanbanService.php',
            'Config/config.php',
            'Http/routes.php',
            'Console/HealthCommand.php',
            'Public/img/module.png',
        ];
        $missing = [];
        foreach ($relative as $path) {
            if (!is_file($root.'/'.$path) || !is_readable($root.'/'.$path)) {
                $missing[] = $path;
            }
        }

        return $missing ? 'missing '.implode(', ', $missing) : 'ok';
    }

    protected function classes(): string
    {
        $classes = [
            'Modules\\KanbanWorkflow\\Providers\\KanbanWorkflowServiceProvider',
            'Modules\\KanbanWorkflow\\Services\\KanbanService',
            'Modules\\KanbanWorkflow\\Console\\HealthCommand',
        ];
        $missing = [];
        foreach ($classes as $class) {
            if (!class_exists($class)) {
                $missing[] = $class;
            }
        }

        return $missing ? 'missing '.implode(', ', $missing) : 'ok';
    }

    protected function versionsMatch(string $root): string
    {
        $manifestVersion = $this->manifestVersion($root);
        $file = $root.'/version.txt';
        if (!is_file($file) || !is_readable($file)) {
            return 'version.txt missing';
        }
        $fileVersion = trim((string) file_get_contents($file));
        if ($manifestVersion === '' || $fileVersion !== $manifestVersion) {
            return 'version.txt does not match module.json';
        }

        return 'ok';
    }

    protected function manifestVersion(string $root): string
    {
        $manifest = json_decode((string) file_get_contents($root.'/module.json'), true);
        if (!is_array($manifest) || !isset($manifest['version'])) {
            return '';
        }

        return trim((string) $manifest['version']);
    }
}
