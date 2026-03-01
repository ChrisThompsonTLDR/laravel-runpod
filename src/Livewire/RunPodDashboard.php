<?php

namespace ChrisThompsonTLDR\LaravelRunPod\Livewire;

use ChrisThompsonTLDR\LaravelRunPod\RunPodStatsWriter;
use Livewire\Component;

class RunPodDashboard extends Component
{
    public string $instance = 'default';

    public ?array $stats = null;

    public function mount(?string $instance = null): void
    {
        $this->instance = $instance ?? 'default';
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->stats = app(RunPodStatsWriter::class)->read($this->instance);
    }

    public function render()
    {
        return view('runpod::livewire.runpod-dashboard');
    }
}
