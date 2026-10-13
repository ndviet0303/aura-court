<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ResaleReceiver } from '../types/marketplace'

const emit = defineEmits<{
  /** The cleaned account, or null while any field is invalid. */
  (e: 'update:receiver', receiver: ResaleReceiver | null): void
}>()

const { t } = useI18n()
const form = reactive<ResaleReceiver>({ bank_code: '', account_number: '', account_name: '' })

// Mirrors the server rules for the seller's receiving account.
const issues = computed(() => ({
  bank_code: !/^[A-Za-z0-9_-]{1,32}$/.test(form.bank_code.trim()),
  account_number: !/^\d{4,34}$/.test(form.account_number.trim()),
  account_name: !form.account_name.trim() || form.account_name.trim().length > 160,
}))

watch(
  form,
  () => {
    const invalid = Object.values(issues.value).some(Boolean)
    emit(
      'update:receiver',
      invalid
        ? null
        : {
            bank_code: form.bank_code.trim().toUpperCase(),
            account_number: form.account_number.trim(),
            account_name: form.account_name.trim(),
          },
    )
  },
  { immediate: true },
)
</script>

<template>
  <fieldset class="receiver-box" data-testid="resale-receiver-form">
    <legend class="legend">{{ t('resale.p2p.receiverTitle') }}</legend>
    <p class="hint">{{ t('resale.p2p.receiverHint') }}</p>
    <label class="field">
      <span>{{ t('resale.p2p.bankCode') }}</span>
      <input v-model="form.bank_code" class="text-input" maxlength="32" name="bank_code" />
      <small v-if="form.bank_code && issues.bank_code" class="field-error">
        {{ t('resale.p2p.invalidBankCode') }}
      </small>
    </label>
    <label class="field">
      <span>{{ t('resale.p2p.accountNumber') }}</span>
      <input
        v-model="form.account_number"
        class="text-input font-mono"
        inputmode="numeric"
        maxlength="34"
        name="account_number"
      />
      <small v-if="form.account_number && issues.account_number" class="field-error">
        {{ t('resale.p2p.invalidAccountNumber') }}
      </small>
    </label>
    <label class="field">
      <span>{{ t('resale.p2p.accountName') }}</span>
      <input v-model="form.account_name" class="text-input" maxlength="160" name="account_name" />
      <small v-if="form.account_name && issues.account_name" class="field-error">
        {{ t('resale.p2p.invalidAccountName') }}
      </small>
    </label>
  </fieldset>
</template>

<style scoped>
.receiver-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 12px 14px;
  border: 1px solid var(--outline);
  border-radius: 14px;
}

.legend {
  padding: 0 4px;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.hint {
  margin: 0;
  font-size: 11.5px;
  color: var(--muted);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.text-input {
  padding: 9px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
}

.field-error {
  color: #c62828;
  font-size: 11px;
}
</style>
