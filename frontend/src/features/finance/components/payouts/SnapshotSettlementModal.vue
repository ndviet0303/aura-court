<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, DatePicker } from '@/shared/components'

const props = withDefaults(
  defineProps<{
    show: boolean
    loading?: boolean
  }>(),
  {
    loading: false,
  }
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', periodStart: string, periodEnd: string): void
}>()

const { t } = useI18n()

function formatDateISO(d: Date): string {
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

const today = new Date()
const defaultStart = new Date(today)
defaultStart.setDate(defaultStart.getDate() - 15)

const periodStart = ref(formatDateISO(defaultStart))
const periodEnd = ref(formatDateISO(today))

watch(
  () => props.show,
  (val) => {
    if (val) {
      const now = new Date()
      const prev = new Date(now)
      prev.setDate(prev.getDate() - 15)
      periodStart.value = formatDateISO(prev)
      periodEnd.value = formatDateISO(now)
    }
  }
)

function handleFormSubmit() {
  if (!periodStart.value || !periodEnd.value) return
  if (periodEnd.value < periodStart.value) return
  emit('submit', periodStart.value, periodEnd.value)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop aura-modal-backdrop" @click.self="emit('close')">
    <div class="modal-card aura-modal-card modal-md">
      <div class="modal-header">
        <div class="title-row">
          <span class="material-symbols-outlined header-icon text-pink">camera_alt</span>
          <h3 class="modal-title">{{ t('admin.payouts.snapshotModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined icon-20">close</span>
        </button>
      </div>

      <form class="modal-form" @submit.prevent="handleFormSubmit">
        <div class="modal-body">
          <p class="explanation-text">
            {{ t('admin.payouts.snapshotExplanation') }}
          </p>

          <div class="date-range-grid">
            <!-- Period Start DatePicker -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.payouts.periodStartLabel') }}</label>
              <DatePicker
                v-model="periodStart"
                :max="periodEnd"
                placeholder="YYYY-MM-DD"
              />
            </div>

            <!-- Period End DatePicker -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.payouts.periodEndLabel') }}</label>
              <DatePicker
                v-model="periodEnd"
                :min="periodStart"
                placeholder="YYYY-MM-DD"
              />
            </div>
          </div>

          <div class="info-notice">
            <span class="material-symbols-outlined icon-16 text-pink">verified</span>
            <span>Chu kỳ đối soát tuân thủ quy tắc D+2 để đảm bảo các giao dịch nạp và đặt sân được xác thực hoàn tất.</span>
          </div>
        </div>

        <div class="modal-footer">
          <AuraButton type="button" variant="secondary" :disabled="loading" @click="emit('close')">
            {{ t('admin.payouts.cancelBtn') }}
          </AuraButton>

          <AuraButton
            type="submit"
            variant="primary"
            :disabled="loading || !periodStart || !periodEnd || periodEnd < periodStart"
          >
            <span class="material-symbols-outlined icon-16">camera_alt</span>
            <span>{{ t('admin.payouts.confirmSnapshotBtn') }}</span>
          </AuraButton>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 24px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.modal-form {
  display: flex;
  flex-direction: column;
}

.modal-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
}

.explanation-text {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  line-height: 1.5;
}

.date-range-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

.info-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(219, 39, 119, 0.06);
  border: 1px solid rgba(219, 39, 119, 0.15);
  font-size: 12px;
  color: var(--ink);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid var(--outline);
}

.icon-16 {
  font-size: 16px;
}

.icon-20 {
  font-size: 20px;
}

@media (max-width: 640px) {
  .date-range-grid {
    grid-template-columns: 1fr;
  }
}
</style>
