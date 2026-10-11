<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, SelectDropdown, type DropdownOption } from '@/shared/components'
import type { SettlementRecord, BeneficiaryAccount } from '../../types/finance'

const props = withDefaults(
  defineProps<{
    show: boolean
    availableNet: number
    eligibleSettlements?: SettlementRecord[]
    beneficiary?: BeneficiaryAccount
    selectedSettlement?: SettlementRecord | null
    loading?: boolean
  }>(),
  {
    eligibleSettlements: () => [],
    beneficiary: undefined,
    selectedSettlement: null,
    loading: false,
  },
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', settlementId: number, beneficiaryId: number): void
}>()

const { t } = useI18n()

const selectedSettlementId = ref<number | null>(null)
const customAmount = ref<number>(0)

function formatDate(dateStr?: string | null): string {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('vi-VN')
  } catch {
    return dateStr
  }
}

const settlementOptions = computed<DropdownOption[]>(() => {
  return props.eligibleSettlements.map((s) => ({
    value: s.id,
    label: `${formatDate(s.period_start)} → ${formatDate(s.period_end)} (${(s.tenant_payable || s.net_amount || 0).toLocaleString('vi-VN')} ₫)`,
    icon: 'summarize',
  }))
})

const activeSettlement = computed(() => {
  return (
    props.eligibleSettlements.find((s) => s.id === selectedSettlementId.value) ||
    props.eligibleSettlements[0] ||
    null
  )
})

watch(
  () => props.show,
  (val) => {
    if (val) {
      if (props.selectedSettlement?.id) {
        selectedSettlementId.value = props.selectedSettlement.id
      } else if (props.eligibleSettlements.length > 0 && props.eligibleSettlements[0]) {
        selectedSettlementId.value = props.eligibleSettlements[0].id
      }
      customAmount.value = activeSettlement.value?.tenant_payable || props.availableNet || 1000000
    }
  },
  { immediate: true },
)

watch(
  () => selectedSettlementId.value,
  () => {
    if (activeSettlement.value) {
      customAmount.value =
        activeSettlement.value.tenant_payable || activeSettlement.value.net_amount || 0
    }
  },
)

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function handleFormSubmit() {
  const sId = selectedSettlementId.value || activeSettlement.value?.id
  const bId = props.beneficiary?.id || 1
  if (!sId) return
  emit('submit', sId, bId)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop aura-modal-backdrop" @click.self="emit('close')">
    <div class="modal-card aura-modal-card modal-md">
      <div class="modal-header">
        <div class="title-row">
          <span class="material-symbols-outlined header-icon text-pink">payments</span>
          <h3 class="modal-title">{{ t('admin.payouts.createModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined icon-20">close</span>
        </button>
      </div>

      <form class="modal-form" @submit.prevent="handleFormSubmit">
        <div class="modal-body">
          <!-- Settlement Selection with SelectDropdown -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.payouts.selectSettlementLabel') }}</label>
            <div v-if="eligibleSettlements.length > 0" class="dropdown-wrapper">
              <SelectDropdown
                :model-value="selectedSettlementId || undefined"
                :options="settlementOptions"
                :placeholder="t('admin.payouts.selectSettlementPlaceholder')"
                variant="bordered"
                @update:model-value="selectedSettlementId = Number($event)"
              />
            </div>
            <div v-else class="no-eligible-notice">
              <span class="material-symbols-outlined icon-16 text-muted">info</span>
              <span>{{ t('admin.payouts.noEligibleSettlements') }}</span>
            </div>
          </div>

          <!-- Beneficiary Preview Box -->
          <div v-if="beneficiary" class="beneficiary-preview-box">
            <div class="preview-head">
              <span class="material-symbols-outlined icon-16 text-pink">account_balance</span>
              <span class="preview-title">{{ t('admin.payouts.beneficiaryLabel') }}</span>
            </div>
            <div class="preview-body">
              <strong
                >{{ beneficiary.bank_name || beneficiary.bank_code }} ({{
                  beneficiary.account_number
                }})</strong
              >
              <span class="holder-text">{{ beneficiary.account_holder }}</span>
            </div>
          </div>

          <!-- Amount Field -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.payouts.amountLabel') }}</label>
            <div class="input-with-suffix">
              <input
                v-model.number="customAmount"
                type="number"
                min="500000"
                step="10000"
                class="form-input font-mono"
                required
              />
              <span class="input-suffix">VND</span>
            </div>
            <span class="sub-hint">
              {{ t('admin.payouts.availableBalance') }}
              <strong class="text-pink">{{
                formatMoney(activeSettlement?.tenant_payable || availableNet)
              }}</strong>
            </span>
          </div>

          <!-- Minimum threshold notice -->
          <div class="info-callout">
            <span class="material-symbols-outlined icon-16 text-muted">verified_user</span>
            <span>{{ t('admin.payouts.minThresholdNotice') }}</span>
          </div>
        </div>

        <div class="modal-footer">
          <AuraButton type="button" variant="secondary" :disabled="loading" @click="emit('close')">
            {{ t('admin.payouts.cancelBtn') }}
          </AuraButton>

          <AuraButton
            type="submit"
            variant="primary"
            :disabled="loading || !selectedSettlementId || customAmount < 500000"
          >
            <span class="material-symbols-outlined icon-16">send</span>
            <span>{{ t('admin.payouts.confirmCreateBtn') }}</span>
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

.dropdown-wrapper {
  width: 100%;
}

.no-eligible-notice {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--soft, rgba(0, 0, 0, 0.02));
  border: 1px dashed var(--outline);
  font-size: 13px;
  color: var(--muted);
}

.beneficiary-preview-box {
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.preview-head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 700;
  color: var(--muted);
}

.preview-body {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: var(--ink);
  flex-wrap: wrap;
  gap: 6px;
}

.holder-text {
  font-size: 12px;
  color: var(--muted);
}

.input-with-suffix {
  position: relative;
  display: flex;
  align-items: center;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  padding-right: 54px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 15px;
  outline: none;
  transition: border-color 0.2s ease;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.input-suffix {
  position: absolute;
  right: 14px;
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.sub-hint {
  font-size: 12px;
  color: var(--muted);
  display: flex;
  justify-content: space-between;
}

.info-callout {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(59, 130, 246, 0.06);
  border: 1px solid rgba(59, 130, 246, 0.15);
  color: var(--ink);
  font-size: 12px;
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
