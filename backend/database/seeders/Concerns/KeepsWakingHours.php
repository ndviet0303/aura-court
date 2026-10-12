<?php

namespace Database\Seeders\Concerns;

use Carbon\Carbon;

/** Seeded people act while awake, so demo timelines never show a booking, payment, post or friend request at 3am. */
trait KeepsWakingHours
{
    /**
     * $at itself unless it falls between 00:00 and 06:59 local time. Then the nearer of 22:mm the evening before and
     * 07:mm that morning that lies inside [$after, $before] and not after now; failing both, the evening squeezed up to
     * $after or the morning squeezed down to the latest allowed moment; $at itself when nothing awake fits.
     */
    protected function awake(Carbon $at, ?Carbon $after = null, ?Carbon $before = null): Carbon
    {
        $tz = config('app.timezone');
        $local = $at->copy()->setTimezone($tz);
        if ($local->hour >= 7) {
            return $at->copy();
        }

        $evening = $local->copy()->subDay()->setTime(22, $local->minute, $local->second);
        $morning = $local->copy()->setTime(7, $local->minute, $local->second);
        $latest = $before && $before->lt(now()) ? $before : now();
        $candidates = $local->hour < 3 ? [$evening, $morning] : [$morning, $evening];
        array_push($candidates, $after ? $evening->copy()->max($after) : $evening, $morning->copy()->min($latest));
        foreach ($candidates as $candidate) {
            $candidate = $candidate->copy()->setTimezone($tz);
            if ($candidate->hour >= 7 && (! $after || $candidate->gte($after)) && $candidate->lte($latest)) {
                return $candidate->setTimezone($at->getTimezone());
            }
        }

        return $at->copy();
    }
}
