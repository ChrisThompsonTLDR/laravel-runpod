<?php

use ChrisThompsonTLDR\LaravelRunPod\Facades\RunPod;

it('registers the RunPod service and facade', function () {
    $runPod = app(\ChrisThompsonTLDR\LaravelRunPod\RunPod::class);

    expect($runPod)->toBeInstanceOf(\ChrisThompsonTLDR\LaravelRunPod\RunPod::class);
});

it('resolves RunPod facade', function () {
    expect(RunPod::getFacadeRoot())
        ->toBeInstanceOf(\ChrisThompsonTLDR\LaravelRunPod\RunPod::class);
});
