<?php

namespace App\Enums;

enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';

    public function maxMonitors(): int
    {
        return (int) config("plans.{$this->value}.max_monitors");
    }

    public function minInterval(): int
    {
        return (int) config("plans.{$this->value}.min_interval");
    }
 
    public function retentionDays(): int
    {
        return (int) config("plans.{$this->value}.retention_days");
    }
 
    /** @return int[] */
    public function allowedIntervals(): array
    {
        return array_values(array_filter(
            config('plans.intervals'),
            fn (int $seconds) => $seconds >= $this->minInterval(),
        ));
    }
}
