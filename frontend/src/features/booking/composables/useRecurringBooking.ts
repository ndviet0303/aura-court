import { computed, ref, watch } from 'vue'
import { idempotencyKey } from '@/core/api/client'
import { i18n } from '@/i18n'
import type { Court } from '@/features/court/types/court'
import { bookingApi } from '../api/bookingApi'
import type {
  OccurrenceRefusal,
  OccurrenceRow,
  RecurringMode,
  RecurringOccurrencePayload,
} from '../types/recurring'
import type { VoucherQuote } from '../types/reservation'
import { bookingErrorMessage } from '../utils/reservation'
import {
  monthDays,
  pickedDays,
  todayInVenue,
  validTimes,
  venueInstant,
  weekdayDays,
  type PlannedDays,
} from '../utils/recurringDates'

// The backend takes at most this many occurrences per request.
export const MAX_OCCURRENCES = 100

const newRow = (date: string, start: string, end: string): OccurrenceRow => ({
  date,
  start,
  end,
  custom: false,
  selected: true,
  checking: false,
  check: null,
  voucherId: null,
  code: '',
  booked: null,
  refusal: null,
})

// The voucher an occurrence applies: the code entered, else the one picked, else the first that applies (as the
// booking form defaults); 0 is none (BR-170).
export function chosenVoucher(row: OccurrenceRow): VoucherQuote | null {
  const check = row.check
  if (!check?.ok) return null
  if (row.code.trim()) return check.code_voucher
  if (row.voucherId === 0) return null
  const vouchers = check.vouchers ?? []
  return row.voucherId === null
    ? (vouchers[0] ?? null)
    : (vouchers.find((v) => v.id === row.voucherId) ?? null)
}

// What the occurrence costs once checked; null until it is checked and bookable.
export function payable(row: OccurrenceRow): number | null {
  if (!row.check?.ok || (row.code.trim() && !row.check.code_voucher)) return null
  return chosenVoucher(row)?.total ?? row.check.rental
}

export const refusalMessage = (refusal: OccurrenceRefusal) =>
  bookingErrorMessage(refusal, refusal.message)

/**
 * Module 08 (UC-8.1..UC-8.7): plan the days of a recurring booking in one of three modes, give every occurrence the
 * common time or its own, check each one as an ordinary booking (nothing held) and submit the bookable ones the
 * customer picked, each becoming its own booking with its own payment. A refused occurrence is never moved to another
 * day or time.
 */
export function useRecurringBooking() {
  const today = todayInVenue()
  const courts = ref<Court[]>([])
  const courtId = ref<number | null>(null)
  const mode = ref<RecurringMode>('weekdays')
  const month = ref(today.slice(0, 7))
  const weekdays = ref<number[]>([])
  const fromMonth = ref(today.slice(0, 7))
  const toMonth = ref(today.slice(0, 7))
  const days = ref<number[]>([])
  const dates = ref<string[]>([])
  const start = ref('18:00')
  const end = ref('20:00')
  const rows = ref<OccurrenceRow[]>([])
  const loading = ref(false)
  const checking = ref(false)
  const submitting = ref(false)
  const error = ref('')
  // UC-8.4 A-2: once the common time was applied, or an occurrence has its own, applying again asks first.
  const applied = ref(false)
  const confirmingApply = ref(false)

  // BR-164: the end month may not come before the start month.
  const monthRangeInvalid = computed(
    () => mode.value === 'monthDays' && toMonth.value < fromMonth.value,
  )

  const plan = computed<PlannedDays>(() => {
    if (mode.value === 'weekdays') return weekdayDays(month.value, weekdays.value, today)
    if (mode.value === 'monthDays')
      return monthRangeInvalid.value
        ? { dates: [], skipped: [] }
        : monthDays(fromMonth.value, toMonth.value, days.value, today)
    return pickedDays(dates.value, today)
  })

  // A new plan keeps the bookings already made (their days are not planned again) and an occurrence still planned,
  // with its own time. A list made again from weekdays or month days is checked again (UC-8.1 A-2, UC-8.2 A-2); a
  // day picked or dropped on the calendar leaves the other days' checks (UC-8.3 A-1, A-2).
  watch(
    () => plan.value.dates,
    (planned) => {
      const booked = rows.value.filter((row) => row.booked)
      const kept = new Map(rows.value.filter((row) => !row.booked).map((row) => [row.date, row]))
      if (mode.value !== 'dates') kept.forEach(reset)
      const bookedDays = new Set(booked.map((row) => row.date))
      const next = planned
        .filter((date) => !bookedDays.has(date))
        .map((date) => kept.get(date) ?? newRow(date, start.value, end.value))
      rows.value = [...booked, ...next].sort((a, b) =>
        (a.date + a.start).localeCompare(b.date + b.start),
      )
    },
  )

  const open = computed(() => rows.value.filter((row) => !row.booked))

  // Another court means every open occurrence is checked again.
  watch(courtId, () => open.value.forEach(reset))

  function reset(row: OccurrenceRow) {
    row.check = null
    row.refusal = null
  }

  // FR-135: the common time applies to every occurrence not yet booked.
  const commonTimeValid = computed(() => validTimes(start.value, end.value))
  function requestApply() {
    if (!commonTimeValid.value) return
    if (applied.value || open.value.some((row) => row.custom)) confirmingApply.value = true
    else applyTime()
  }

  // UC-8.4 steps 5-7: every open occurrence, its own times included, takes the common time and is checked again.
  function applyTime() {
    confirmingApply.value = false
    if (!commonTimeValid.value) return
    applied.value = true
    open.value.forEach((row) => {
      row.start = start.value
      row.end = end.value
      row.custom = false
      reset(row)
    })
    void check()
  }

  function payload(row: OccurrenceRow): RecurringOccurrencePayload {
    const voucher = chosenVoucher(row)
    return {
      starts_at: venueInstant(row.date, row.start),
      ends_at: venueInstant(row.date, row.end),
      ...(row.code.trim()
        ? { voucher_code: row.code.trim() }
        : voucher
          ? { voucher_program_id: voucher.id }
          : {}),
    }
  }

  function guard(targets: OccurrenceRow[]): boolean {
    error.value = ''
    if (!courtId.value || targets.length === 0) return false
    if (targets.length > MAX_OCCURRENCES) {
      error.value = i18n.global.t('recurring.tooMany', { max: MAX_OCCURRENCES })
      return false
    }
    return true
  }

  // What a request was sent for; an answer is applied only to a row still the same (another court, time or code
  // asked again meanwhile).
  const sentAs = (row: OccurrenceRow) =>
    `${courtId.value}|${row.date}|${row.start}|${row.end}|${row.code.trim()}`

  // UC-8.6 E-3: a code is checked together with every picked occurrence naming it, so a single-use code named on
  // several of them shows as a conflict on each. A request carries one code, so each code goes in its own request;
  // occurrences without a code ride along with the first, and a code on an occurrence not picked is checked alone.
  const codeOf = (row: OccurrenceRow) => row.code.trim().toUpperCase()
  const pickedWith = (code: string) =>
    open.value.filter((other) => other.selected && codeOf(other) === code)
  function batches(targets: OccurrenceRow[]): OccurrenceRow[][] {
    const byCode = new Map<string, Set<OccurrenceRow>>()
    const alone: OccurrenceRow[][] = []
    const plain: OccurrenceRow[] = []
    for (const row of targets) {
      const code = codeOf(row)
      if (!code) plain.push(row)
      else if (!row.selected) alone.push([row])
      else byCode.set(code, new Set([...(byCode.get(code) ?? []), row, ...pickedWith(code)]))
    }
    const groups = [...[...byCode.values()].map((group) => [...group]), ...alone]
    if (groups[0]) groups[0].push(...plain)
    else groups.push(plain)
    return groups
  }

  // FR-136, BR-169: checks the given occurrences (by default every picked one not yet checked).
  async function check(targets = open.value.filter((row) => row.selected && !row.check)) {
    for (const batch of batches(targets)) await send(batch)
  }

  async function send(targets: OccurrenceRow[]) {
    targets = targets.filter((row) => validTimes(row.start, row.end))
    if (!guard(targets)) return
    checking.value = true
    targets.forEach((row) => (row.checking = true))
    const sent = targets.map(sentAs)
    try {
      const res = await bookingApi.checkRecurringBookings({
        court_id: courtId.value!,
        occurrences: targets.map((row) => ({
          starts_at: venueInstant(row.date, row.start),
          ends_at: venueInstant(row.date, row.end),
          ...(row.code.trim() ? { voucher_code: row.code.trim() } : {}),
        })),
      })
      res.data.forEach((result, i) => {
        const row = targets[i]
        // A fresh answer replaces why an earlier submit was refused.
        if (row && sentAs(row) === sent[i]) Object.assign(row, { check: result, refusal: null })
      })
    } catch (cause) {
      error.value = bookingErrorMessage(cause)
    } finally {
      targets.forEach((row) => (row.checking = false))
      checking.value = false
    }
  }

  // FR-136, BR-171: one occurrence's own time; it is checked and priced again (with the occurrences sharing its code).
  function setTime(row: OccurrenceRow, startsAt: string, endsAt: string) {
    row.start = startsAt
    row.end = endsAt
    row.custom = true
    reset(row)
    if (validTimes(startsAt, endsAt)) void check([row])
  }

  function setVoucher(row: OccurrenceRow, id: number) {
    row.voucherId = id
    row.code = ''
  }

  // UC-8.6 E-3: the occurrences that named the previous code are checked again too.
  async function setCode(row: OccurrenceRow, code: string) {
    const previous = codeOf(row)
    row.code = code
    if (!row.check?.ok) return
    await check([row])
    if (previous && previous !== codeOf(row)) await check(pickedWith(previous))
  }

  // A picked occurrence is checked if it never was; dropping one that names a code frees the code for the others.
  function setSelected(row: OccurrenceRow, selected: boolean) {
    row.selected = selected
    const code = codeOf(row)
    if (code) void check([row, ...pickedWith(code)])
    else if (selected && !row.check) void check([row])
  }

  let submitKey = { body: '', key: '' }
  // UC-8.7 A-1: only the occurrences the customer picked are submitted; the others are neither booked nor held.
  const ready = computed(() => open.value.filter((row) => row.selected && payable(row) !== null))
  const total = computed(() => ready.value.reduce((sum, row) => sum + (payable(row) ?? 0), 0))

  // FR-138, BR-172, BR-174: each picked, ready occurrence becomes its own booking; a refused one shows why and is
  // checked again before another try.
  async function submit() {
    const targets = [...ready.value]
    if (!guard(targets)) return
    submitting.value = true
    const sent = targets.map((row) => ({ as: sentAs(row), start: row.start, end: row.end }))
    const body = {
      court_id: courtId.value!,
      occurrences: targets.map((row) => ({ ...payload(row), expected_total: payable(row)! })),
    }
    // A retry of the same request after a lost answer reuses its key, so the server replays what it booked.
    const json = JSON.stringify(body)
    if (submitKey.body !== json) submitKey = { body: json, key: idempotencyKey() }
    try {
      const res = await bookingApi.submitRecurringBookings(body, submitKey.key)
      submitKey = { body: '', key: '' }
      res.data.forEach((result, i) => {
        const row = targets[i]
        const was = sent[i]
        if (!row || !was) return
        if (result.reservation) {
          // The booking is for the time sent, whatever the row shows now.
          Object.assign(row, { booked: result.reservation, start: was.start, end: was.end })
        } else if (sentAs(row) === was.as) {
          row.check = null
          row.refusal = result.refusal
        }
      })
    } catch (cause) {
      error.value = bookingErrorMessage(cause)
    } finally {
      submitting.value = false
    }
  }

  async function load() {
    loading.value = true
    try {
      courts.value = await bookingApi.getCourts<Court[]>()
      courtId.value ??= courts.value[0]?.id ?? null
    } catch (cause) {
      error.value = bookingErrorMessage(cause)
    } finally {
      loading.value = false
    }
  }

  return {
    busy: computed(() => checking.value || submitting.value),
    today,
    courts,
    courtId,
    mode,
    month,
    weekdays,
    fromMonth,
    toMonth,
    days,
    dates,
    start,
    end,
    plan,
    monthRangeInvalid,
    rows,
    open,
    ready,
    total,
    loading,
    checking,
    submitting,
    error,
    commonTimeValid,
    confirmingApply,
    requestApply,
    applyTime,
    check,
    setTime,
    setVoucher,
    setCode,
    setSelected,
    submit,
    load,
  }
}
