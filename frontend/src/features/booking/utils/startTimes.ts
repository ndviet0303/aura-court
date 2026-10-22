import type { AvailabilitySlot } from '@/features/court/types/court'
import { venueClock } from '@/core/utils/venueTime'

export type StartPeriod = 'morning' | 'afternoon' | 'evening'

export type StartTime = {
  run: AvailabilitySlot[]
  startsAt: string
  clock: string
  price: number
  period: StartPeriod
  peak: boolean
}

/**
 * The start times a customer can book for `minutes`: each begins a free, unbroken run of 30-minute
 * slots that ends exactly `minutes` later, and none has already started (BR-75).
 */
export function startTimes(
  slots: AvailabilitySlot[],
  minutes: number,
  now = Date.now(),
): StartTime[] {
  const ordered = [...slots].sort((a, b) => Date.parse(a.startsAt) - Date.parse(b.startsAt))
  const starts: StartTime[] = []
  ordered.forEach((first, i) => {
    const begin = Date.parse(first.startsAt)
    if (!first.available || begin <= now) return
    const end = begin + minutes * 60000
    const run = [first]
    for (let j = i + 1; Date.parse(run[run.length - 1]!.endsAt) < end; j++) {
      const next = ordered[j]
      if (!next?.available || Date.parse(next.startsAt) !== Date.parse(run[run.length - 1]!.endsAt))
        return
      run.push(next)
    }
    if (Date.parse(run[run.length - 1]!.endsAt) !== end) return
    const clock = venueClock(first.startsAt)
    const hour = Number(clock.slice(0, 2))
    starts.push({
      run,
      startsAt: first.startsAt,
      clock,
      price: run.reduce((sum, slot) => sum + slot.price, 0),
      period: hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening',
      peak: hour >= 17 && hour <= 21,
    })
  })
  return starts
}
