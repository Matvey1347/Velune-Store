<?php

namespace WPStripePayments\Core;

class Loader
{
    /** @var array<int, array<string, mixed>> */
    private array $actions = [];

    /** @var array<int, array<string, mixed>> */
    private array $filters = [];

    public function addAction(string $hook, object $component, string $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->actions[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
        ];
    }

    public function addFilter(string $hook, object $component, string $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->filters[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
        ];
    }

    public function run(): void
    {
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
