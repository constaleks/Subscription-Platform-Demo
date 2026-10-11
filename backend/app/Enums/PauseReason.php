<?php

namespace App\Enums;

enum PauseReason: string
{
    case User = 'user';
    case PlanLimit = 'plan_limit';
}
