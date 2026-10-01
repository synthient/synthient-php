<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * The activity pattern of a domain over the last 30 days, as a sentence.
 */
final class Cadence
{
    public const string NO_ACTIVITY = 'No activity in the last 30 days.';
    public const string EVENLY_DISTRIBUTED = 'Activity is evenly distributed across the day.';
    public const string BUSINESS_HOURS = 'Activity follows a weekday business-hours pattern.';
    public const string WEEKEND = 'Activity follows a weekend pattern.';
    public const string BURST_DRIVEN = 'Activity is burst-driven with significant peaks.';
}
