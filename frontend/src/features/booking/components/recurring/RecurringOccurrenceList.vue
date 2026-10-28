<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency, formatDateTime } from '@/i18n'
import type { OccurrenceRow } from '../../types/recurring'
import { chosenVoucher, payable, refusalMessage } from '../../composables/useRecurringBooking'
import { validTimes } from '../../utils/recurringDates'
import RecurringTimeDialog from './RecurringTimeDialog.vue'

// FR-136..FR-138: every occurrence with whether it is picked for submitting (UC-8.7 A-1), its own time, its check,
// its voucher and, once submitted, its booking or why it was refused (BR-169, BR-174). Nothing here moves an
// occurrence to another day or time.
defineProps<{ rows: OccurrenceRow[]; disabled: boolean }>()
const emit = defineEmits<{
  (e: 'time', row: OccurrenceRow, start: string, end: string): void
  (e: 'voucher', row: OccurrenceRow, id: number): void
  (e: 'code', row: OccurrenceRow, code: string): void
  (e: 'select', row: OccurrenceRow, selected: boolean): void
}>()

const { t } = useI18n()
const weekday = (date: string) =>
  t(`recurring.weekday.${new Date(`${date}T00:00:00Z`).getUTCDay() || 7}`)
const shortDate = (date: string) => `${date.slice(8)}/${date.slice(5, 7)}/${date.slice(0, 4)}`
const selectedVoucher = (row: OccurrenceRow) => chosenVoucher(row)?.id ?? 0

// UC-8.5: the occurrence whose time is being changed in the dialog.
const editing = ref<OccurrenceRow | null>(null)
function confirmTime(start: string, end: string) {
  if (editing.value) emit('time', editing.value, start, end)
  editing.value = null
}
</script>

<template>
  <ol class="occurrences" data-testid="recurring-occurrences">
    <li
      v-for="row in rows"
      :key="`${row.date}-${row.booked?.code ?? 'open'}`"
      class="occurrence"
      :class="{ booked: row.booked, refused: row.refusal || row.check?.refusal }"
      :data-testid="`occurrence-${row.date}`"
    >
      <div class="occ-when">
        <input
          v-if="!row.booked"
          type="checkbox"
          :checked="row.selected"
          :disabled="disabled"
          :aria-label="t('recurring.select', { date: shortDate(row.date) })"
          :data-testid="`occurrence-select-${row.date}`"
          @change="emit('select', row, ($event.target as HTMLInputElement).checked)"
        />
        <strong>{{ weekday(row.date) }} {{ shortDate(row.date) }}</strong>
        <span>{{ row.start }} – {{ row.end }}</span>
        <button
          v-if="!row.booked"
          type="button"
          class="occ-edit"
          :disabled="disabled"
          :data-testid="`occurrence-edit-${row.date}`"
          @click="editing = row"
        >
          {{ t('recurring.editTime') }}
        </button>
      </div>

      <div v-if="row.booked" class="occ-state">
        <span class="badge ok">{{ t('recurring.booked', { code: row.booked.code }) }}</span>
        <span>{{
          t(row.booked.status === 'confirmed' ? 'booking.statusConfirmed' : 'booking.statusPending')
        }}</span>
        <span>{{ formatCurrency(row.booked.total_amount) }}</span>
        <span v-if="row.booked.status === 'held' && row.booked.hold_expires_at">
          {{ t('recurring.payBy', { time: formatDateTime(row.booked.hold_expires_at) }) }}
        </span>
        <RouterLink :to="{ name: 'reservations', params: { code: row.booked.code } }">
          {{
            t(row.booked.status === 'held' ? 'recurring.viewBooking' : 'recurring.viewConfirmed')
          }}
        </RouterLink>
      </div>
      <div v-else class="occ-state">
        <p v-if="row.refusal" class="occ-refusal" role="alert">{{ refusalMessage(row.refusal) }}</p>
        <span v-if="!validTimes(row.start, row.end)" class="badge bad">{{
          t('recurring.timeInvalid')
        }}</span>
        <span v-else-if="row.checking" class="badge">{{ t('recurring.checking') }}</span>
        <span v-else-if="!row.check" class="badge">{{ t('recurring.unchecked') }}</span>
        <p v-else-if="row.check.refusal" class="occ-refusal" role="alert">
          {{ refusalMessage(row.check.refusal) }}
        </p>
        <template v-else>
          <select
            v-if="row.check.vouchers?.length && !row.code.trim()"
            :value="selectedVoucher(row)"
            :disabled="disabled"
            :aria-label="t('recurring.voucher')"
            @change="emit('voucher', row, Number(($event.target as HTMLSelectElement).value))"
          >
            <option :value="0">{{ t('recurring.noVoucher') }}</option>
            <option v-for="v in row.check.vouchers" :key="v.id" :value="v.id">
              {{ v.name }} (-{{ formatCurrency(v.discount) }})
            </option>
          </select>
          <input
            :value="row.code"
            :disabled="disabled"
            :placeholder="t('recurring.voucherCode')"
            :aria-label="t('recurring.voucherCode')"
            maxlength="64"
            @change="emit('code', row, ($event.target as HTMLInputElement).value)"
          />
          <span v-if="row.check.code_refusal" class="occ-refusal">
            {{ refusalMessage(row.check.code_refusal) }}
          </span>
          <span class="occ-money">
            {{ t('recurring.rental', { amount: formatCurrency(row.check.rental ?? 0) }) }}
            <template v-if="chosenVoucher(row)">
              ·
              {{
                t('recurring.discount', {
                  name: chosenVoucher(row)!.name,
                  amount: formatCurrency(chosenVoucher(row)!.discount),
                })
              }}
            </template>
          </span>
          <strong v-if="payable(row) !== null" class="occ-amount">
            {{ t('recurring.payable', { amount: formatCurrency(payable(row)!) }) }}
          </strong>
        </template>
      </div>
    </li>
  </ol>
  <RecurringTimeDialog
    v-if="editing"
    :row="editing"
    :label="`${weekday(editing.date)} ${shortDate(editing.date)}`"
    @confirm="confirmTime"
    @close="editing = null"
  />
</template>

<style scoped>
.occurrences {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.occurrence {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 16px;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border: 1px solid var(--outline);
  border-radius: 10px;
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}
.occurrence.booked {
  border-color: #16a34a;
}
.occurrence.refused {
  border-color: #dc2626;
}
.occ-when,
.occ-state {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
select,
input:not([type='checkbox']) {
  min-width: 0;
  padding: 5px 8px;
  border: 1px solid var(--outline);
  border-radius: 7px;
  background: var(--surface);
  color: var(--ink);
  font: inherit;
  font-size: 12px;
}
select {
  width: auto;
  min-height: 0;
  padding-right: 28px;
  background-position: right 8px center;
}
input:not([type='checkbox']) {
  width: 120px;
}
.badge {
  padding: 3px 8px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--muted) 14%, transparent);
  color: var(--muted);
  font-size: 11px;
}
.badge.ok {
  background: color-mix(in srgb, #16a34a 14%, transparent);
  color: #16a34a;
}
.badge.bad,
.occ-refusal {
  margin: 0;
  color: #dc2626;
  font-size: 12px;
}
.occ-edit {
  padding: 3px 8px;
  border: 1px solid var(--outline);
  border-radius: 7px;
  background: transparent;
  color: var(--primary);
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}
.occ-money {
  color: var(--muted);
  font-size: 12px;
}
.occ-amount {
  white-space: nowrap;
}
</style>
