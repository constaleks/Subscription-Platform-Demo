<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:dispatch-due-monitors')
    ->everyMinute()
    ->withoutOverlapping(5);