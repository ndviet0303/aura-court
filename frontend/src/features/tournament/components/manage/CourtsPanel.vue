<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { venueClockRange } from '@/core/utils/venueTime'
import { AuraButton, DateTimePicker, SelectDropdown } from '@/shared/components'
import type { ManagedTournament } from '../../composables/useManagedTournament'
import { fromPicker } from '../../utils/tournament'

// UC-7.30, BR-152: a public tournament reserves court time; an internal one plays in its bookings.
const props = defineProps<{ manage: ManagedTournament }>()
const { t, d } = useI18n()
const { data, can, busy } = props.manage
const draft = reactive({ court_id: null as number | null, starts_at: '', ends_at: '' })
const courtOptions = computed(() =>
  (data.value?.courts ?? []).map((c) => ({ value: c.id, label: c.code })),
)
const slots = computed(() => [
  ...(data.value?.allocations ?? []).map((a) => ({ ...a, key: `a${a.id}`, code: null })),
  ...(data.value?.rentals ?? []).map((r) => ({
    ...r,
    id: null,
    key: r.reservation_code,
    code: r.reservation_code,
  })),
])

async function add() {
  if (!draft.court_id || !draft.starts_at || !draft.ends_at) return
  const ok = await props.manage.allocate({
    court_id: draft.court_id,
    starts_at: fromPicker(draft.starts_at)!,
    ends_at: fromPicker(draft.ends_at)!,
  })
  if (ok) Object.assign(draft, { starts_at: '', ends_at: '' })
}
function release(id: number) {
  if (window.confirm(t('tournament.courts.releaseConfirm'))) void props.manage.release(id)
}
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('tournament.courts.title') }}</h2>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>{{ t('tournament.fixture.court') }}</th>
            <th>{{ t('tournament.fixture.time') }}</th>
            <th>{{ t('tournament.courts.source') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="slot in slots" :key="slot.key">
            <td>{{ slot.court.code }}</td>
            <td>
              {{ d(new Date(slot.starts_at), 'date') }}
              {{ venueClockRange(slot.starts_at, slot.ends_at) }}
            </td>
            <td>
              {{
                slot.code
                  ? t('tournament.courts.rental', { code: slot.code })
                  : t('tournament.courts.allocation')
              }}
            </td>
            <td>
              <AuraButton
                v-if="slot.id && can('allocate')"
                variant="ghost"
                size="sm"
                :disabled="busy"
                @click="release(slot.id)"
              >
                {{ t('common.delete') }}
              </AuraButton>
            </td>
          </tr>
          <tr v-if="!slots.length">
            <td colspan="4" class="admin-detail-muted">{{ t('tournament.courts.empty') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <form v-if="can('allocate')" class="courts-add" @submit.prevent="add">
      <div class="form-group">
        <span class="form-label">{{ t('tournament.fixture.court') }}</span>
        <SelectDropdown
          :model-value="draft.court_id"
          :options="courtOptions"
          variant="bordered"
          :placeholder="t('tournament.courts.pickCourt')"
          :aria-label="t('tournament.fixture.court')"
          @update:model-value="draft.court_id = Number($event)"
        />
      </div>
      <div class="form-group">
        <span class="form-label">{{ t('tournament.courts.from') }}</span>
        <DateTimePicker v-model="draft.starts_at" />
      </div>
      <div class="form-group">
        <span class="form-label">{{ t('tournament.courts.to') }}</span>
        <DateTimePicker v-model="draft.ends_at" />
      </div>
      <AuraButton
        type="submit"
        :loading="busy"
        :disabled="!draft.court_id || !draft.starts_at || !draft.ends_at"
      >
        {{ t('tournament.courts.add') }}
      </AuraButton>
    </form>
  </section>
</template>

<style scoped>
.courts-add {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
  margin-top: 16px;
}
</style>
