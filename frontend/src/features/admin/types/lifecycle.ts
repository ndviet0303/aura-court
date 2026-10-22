export type LifecycleCommand = 'open' | 'pause' | 'resume' | 'close'

export type StopCommand = Extract<LifecycleCommand, 'pause' | 'close'>

export type LifecycleTarget =
  { type: 'venue'; id: number; organizationId: number } | { type: 'court'; id: number }

/** Body of pause/close; close never sends kind or expected_end_at. Times carry the venue offset. */
export type StopPayload = {
  reason: string
  starts_at?: string
  expected_end_at?: string
  kind?: 'maintenance' | 'business'
  /** Design D7: sent only after the actor acknowledged the affected bookings. */
  acknowledge_affected?: true
}

/** One unmet opening condition from a 422; the backend already localizes message. */
export type MissingItem = {
  code: string
  message: string
  [key: string]: unknown
}
