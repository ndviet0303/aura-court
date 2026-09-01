<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'create', payload: any): void
}>()

const { t } = useI18n()

const customerName = ref('')
const phone = ref('')
const courtName = ref('Sân 1 (Sân Huấn Luyện)')
const weekday = ref('Thứ 2, 4, 6')
const timeSlot = ref('18:00 - 20:00')
const monthlyAmount = ref(4800000)

function handleCreate() {
  if (!customerName.value.trim() || !phone.value.trim()) return
  emit('create', {
    customer_name: customerName.value.trim(),
    phone: phone.value.trim(),
    court_name: courtName.value,
    weekday: weekday.value,
    time_slot: timeSlot.value,
    total_monthly_amount: monthlyAmount.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">note_add</span>
          <h3 class="modal-title">{{ t('admin.contracts.createContractTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <form @submit.prevent="handleCreate">
        <div class="modal-body">
          <div class="create-form-grid">
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.customerNameLabel') }}</label>
              <input v-model="customerName" type="text" class="form-input" :placeholder="t('admin.contracts.customerNamePlaceholder')" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.phoneNumberLabel') }}</label>
              <input v-model="phone" type="tel" class="form-input font-mono" placeholder="0901 234 567" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.selectCourtLabel') }}</label>
              <input v-model="courtName" type="text" class="form-input" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.weekdayScheduleLabel') }}</label>
              <input v-model="weekday" type="text" class="form-input" :placeholder="t('admin.contracts.weekdaySchedulePlaceholder')" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.fixedTimeSlotLabel') }}</label>
              <input v-model="timeSlot" type="text" class="form-input font-mono" placeholder="18:00 - 20:00" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.monthlyContractValueLabel') }}</label>
              <input v-model.number="monthlyAmount" type="number" step="100000" class="form-input font-mono" />
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.cancel') }}</button>
          <button
            type="submit"
            class="btn-primary"
            :disabled="!customerName.trim() || !phone.trim()"
          >
            {{ t('admin.contracts.signAndActivateBtn') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.create-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
