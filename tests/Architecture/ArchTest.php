<?php

arch()
    ->expect('ChrisThompsonTLDR\LaravelRunPod')
    ->not->toUse(['die', 'dd', 'dump']);

arch()
    ->expect('ChrisThompsonTLDR\LaravelRunPod\RunPod')
    ->toBeClasses()
    ->toExtendNothing()
    ->toImplementNothing();
