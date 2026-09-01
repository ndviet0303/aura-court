<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, SelectDropdown, type DropdownOption } from '@/shared/components'
import type { BeneficiaryAccount } from '../../types/finance'

const props = withDefaults(
  defineProps<{
    show: boolean
    beneficiary?: BeneficiaryAccount
    loading?: boolean
  }>(),
  {
    beneficiary: undefined,
    loading: false,
  }
)

const emit = defineEmits<{
  (e: 'close'): void
  (
    e: 'submit',
    payload: { bank_code: string; account_number: string; account_holder: string; tax_id?: string }
  ): void
}>()

const { t } = useI18n()

const bankCode = ref('VCB')
const accountNumber = ref('')
const accountHolder = ref('')
const taxId = ref('')

const bankOptions = computed<DropdownOption[]>(() => [
  { value: 'VCB', label: 'Vietcombank (VCB)', icon: 'account_balance' },
  { value: 'TCB', label: 'Techcombank (TCB)', icon: 'account_balance' },
  { value: 'MB', label: 'MBBank (MB)', icon: 'account_balance' },
  { value: 'ACB', label: 'ACB (Ngân hàng Á Châu)', icon: 'account_balance' },
  { value: 'BIDV', label: 'BIDV (Đầu tư & Phát triển)', icon: 'account_balance' },
  { value: 'CTG', label: 'VietinBank (Công Thương)', icon: 'account_balance' },
  { value: 'VPB', label: 'VPBank (Việt Nam Thịnh Vượng)', icon: 'account_balance' },
  { value: 'TPB', label: 'TPBank (Tiên Phong)', icon: 'account_balance' },
  { value: 'STB', label: 'Sacombank (Sài Gòn Thương Tín)', icon: 'account_balance' },
  { value: 'HDB', label: 'HDBank (Phát triển TP.HCM)', icon: 'account_balance' },
  { value: 'VIB', label: 'VIB (Quốc Tế)', icon: 'account_balance' },
  { value: 'MSB', label: 'MSB (Hàng Hải)', icon: 'account_balance' },
])

watch(
  () => props.beneficiary,
  (b) => {
    if (b) {
      bankCode.value = b.bank_code || 'VCB'
      accountNumber.value = b.account_number || ''
      accountHolder.value = b.account_holder || ''
      taxId.value = b.tax_id || ''
    }
  },
  { immediate: true }
)

function handleHolderInput(event: Event) {
  const input = event.target as HTMLInputElement
  accountHolder.value = input.value.toUpperCase()
}

function handleFormSubmit() {
  if (!accountNumber.value.trim() || !accountHolder.value.trim()) return
  emit('submit', {
    bank_code: bankCode.value,
    account_number: accountNumber.value.trim(),
    account_holder: accountHolder.value.trim(),
    tax_id: taxId.value.trim(),
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop aura-modal-backdrop" @click.self="emit('close')">
    <div class="modal-card aura-modal-card modal-md">
      <div class="modal-header">
        <div class="title-row">
          <span class="material-symbols-outlined header-icon text-pink">account_balance</span>
          <h3 class="modal-title">{{ t('admin.payouts.editModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined icon-20">close</span>
        </button>
      </div>

      <form class="modal-form" @submit.prevent="handleFormSubmit">
        <div class="modal-body">
          <!-- Bank Selector with SelectDropdown -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.payouts.selectBankLabel') }}</label>
            <SelectDropdown
              :model-value="bankCode"
              :options="bankOptions"
              :placeholder="t('admin.payouts.selectBankPlaceholder')"
              variant="bordered"
              @update:model-value="bankCode = String($event)"
            />
          </div>

          <!-- Account Number Field -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.payouts.accountNumberField') }}</label>
            <input
              v-model="accountNumber"
              type="text"
              class="form-input font-mono"
              placeholder="0123456789..."
              required
            />
          </div>

          <!-- Account Holder Field (Uppercase) -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.payouts.accountHolderField') }}</label>
            <input
              :value="accountHolder"
              type="text"
              class="form-input"
              placeholder="CONG TY TNHH / NGUYEN VAN A..."
              required
              @input="handleHolderInput"
            />
          </div>

          <!-- Tax ID / CCCD Field -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.payouts.taxIdField') }}</label>
            <input
              v-model="taxId"
              type="text"
              class="form-input font-mono"
              placeholder="0317892110 / 079092008892"
            />
          </div>
        </div>

        <div class="modal-footer">
          <AuraButton type="button" variant="secondary" :disabled="loading" @click="emit('close')">
            {{ t('admin.payouts.cancelBtn') }}
          </AuraButton>

          <AuraButton
            type="submit"
            variant="primary"
            :disabled="loading || !accountNumber.trim() || !accountHolder.trim()"
          >
            <span class="material-symbols-outlined icon-16">save</span>
            <span>{{ t('admin.payouts.saveBeneficiaryBtn') }}</span>
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
  gap: 14px;
  padding: 20px;
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

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s ease;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
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
</style>
