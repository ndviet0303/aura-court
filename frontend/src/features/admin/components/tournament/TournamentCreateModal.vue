<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, SelectDropdown, DatePicker, type DropdownOption } from '@/shared/components'

defineProps<{
  show: boolean
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'create', payload: any): void
}>()

const { t } = useI18n()

const name = ref('')
const sport = ref('badminton')
const format = ref('single_elimination')
const category = ref('')
const venue = ref('Aura Sport Complex')
const city = ref('TP. Hồ Chí Minh')
const prizePool = ref('20.000.000 ₫')
const entryFee = ref('300.000 ₫ / VĐV')
const maxSlots = ref(16)
const startDate = ref<string>(new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0] ?? '')
const endDate = ref<string>(new Date(Date.now() + 10 * 86400000).toISOString().split('T')[0] ?? '')
const description = ref('')

const sportOptions = computed<DropdownOption[]>(() => [
  { value: 'badminton', label: t('admin.tournaments.sportBadminton') || 'Cầu Lông (Badminton)' },
  { value: 'pickleball', label: t('admin.tournaments.sportPickleball') || 'Pickleball' },
  { value: 'tennis', label: t('admin.tournaments.sportTennis') || 'Quần Vợt (Tennis)' },
  { value: 'football_5v5', label: t('admin.tournaments.sportFootball') || 'Bóng Đá 5v5' },
])

const formatOptions = computed<DropdownOption[]>(() => [
  {
    value: 'single_elimination',
    label: t('admin.tournaments.formatSingleElim') || 'Loại trực tiếp (Single Elimination)',
  },
  {
    value: 'round_robin',
    label: t('admin.tournaments.formatRoundRobin') || 'Vòng tròn tính điểm (Round Robin)',
  },
])

const isFormValid = computed(() => {
  return name.value.trim().length >= 3 && startDate.value && endDate.value
})

function handleSubmit() {
  if (!isFormValid.value) return
  emit('create', {
    name: name.value.trim(),
    sport: sport.value,
    format: format.value,
    category:
      category.value.trim() ||
      (sport.value === 'pickleball' ? 'Pickleball Open' : 'Cầu Lông Đơn Nam'),
    venue: venue.value.trim(),
    city: city.value.trim(),
    prize_pool: prizePool.value.trim(),
    entry_fee: entryFee.value.trim(),
    max_participants: Number(maxSlots.value) || 16,
    start_date: startDate.value,
    end_date: endDate.value,
    description: description.value.trim(),
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">emoji_events</span>
          <div>
            <h2 class="modal-title">{{ t('admin.tournaments.createModalTitle') }}</h2>
            <p class="modal-sub">{{ t('admin.tournaments.createModalSub') }}</p>
          </div>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <!-- Modal Body -->
      <form class="modal-body form-grid" @submit.prevent="handleSubmit">
        <div class="form-group full-width">
          <label class="form-label required">{{
            t('admin.tournaments.tournamentNameLabel')
          }}</label>
          <input
            v-model="name"
            type="text"
            class="form-input"
            :placeholder="t('admin.tournaments.tournamentNamePlaceholder')"
            required
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.sportLabel') }}</label>
          <SelectDropdown v-model="sport" :options="sportOptions" variant="bordered" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.formatLabel') }}</label>
          <SelectDropdown v-model="format" :options="formatOptions" variant="bordered" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.startDateLabel') }}</label>
          <DatePicker v-model="startDate" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.endDateLabel') }}</label>
          <DatePicker v-model="endDate" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.venueLabel') }}</label>
          <input v-model="venue" type="text" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.cityLabel') }}</label>
          <input v-model="city" type="text" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.prizePoolLabel') }}</label>
          <input v-model="prizePool" type="text" class="form-input font-mono" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.entryFeeLabel') }}</label>
          <input v-model="entryFee" type="text" class="form-input font-mono" />
        </div>

        <div class="form-group full-width">
          <label class="form-label">{{ t('admin.tournaments.descriptionLabel') }}</label>
          <textarea
            v-model="description"
            rows="2"
            class="form-input"
            :placeholder="t('admin.tournaments.descriptionPlaceholder')"
          ></textarea>
        </div>
      </form>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton variant="primary" :disabled="!isFormValid || saving" @click="handleSubmit">
          <span v-if="saving" class="material-symbols-outlined icon-18 spin">sync</span>
          <span v-else class="material-symbols-outlined icon-18">add_circle</span>
          <span>{{
            saving ? t('common.saving') : t('admin.tournaments.createTournamentBtn')
          }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 20px;
  width: 100%;
  max-width: 640px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.modal-sub {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted, #64748b);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: var(--muted, #64748b);
}

.modal-body {
  padding: 20px 24px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.full-width {
  grid-column: 1 / -1;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #0f172a);
}

.form-label.required::after {
  content: ' *';
  color: #ef4444;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
  color: var(--ink, #0f172a);
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
  transition: border-color 0.15s;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline, #e2e8f0);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.icon-18 {
  font-size: 18px;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}

@media (max-width: 600px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
