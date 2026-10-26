<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { usePosCashOut } from '../composables/usePosCashOut'

// Cash for a customer's venue credit: enter or scan the one-time code, check the name and amount,
// hand over the cash, then confirm. The confirmation records a cash-out on the open shift.
const props = defineProps<{ shiftId: number }>()
const emit = defineEmits<{ (e: 'close'): void; (e: 'done'): void }>()

const { t, d } = useI18n()
const { code, found, done, busy, error, lookup, confirm, reset } = usePosCashOut(
  () => props.shiftId,
)

async function confirmAndRefresh() {
  await confirm()
  if (done.value) emit('done')
}

function close() {
  reset()
  emit('close')
}
</script>

<template>
  <div class="modal-backdrop" @click.self="close">
    <div class="modal-card" role="dialog" aria-modal="true" data-testid="pos-cash-out-modal">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined modal-icon">currency_exchange</span>
          <h3 class="modal-title">{{ t('admin.pos.cashOutTitle') }}</h3>
        </div>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="close"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <div v-if="done" class="result ok" role="status" data-testid="pos-cash-out-done">
          {{
            t('admin.pos.cashOutDone', {
              amount: formatCurrency(done.amount),
              name: done.customer_name,
              balance: formatCurrency(done.balance),
            })
          }}
        </div>

        <template v-else>
          <form class="form-group" @submit.prevent="lookup">
            <label class="form-label" for="pos-cash-out-code">{{
              t('admin.pos.cashOutCode')
            }}</label>
            <div class="code-row">
              <input
                id="pos-cash-out-code"
                v-model="code"
                type="text"
                maxlength="16"
                autocomplete="off"
                class="form-input code-input"
                :placeholder="t('admin.pos.cashOutCodeHint')"
                autofocus
                data-testid="pos-cash-out-code"
                @input="found = null"
              />
              <button type="submit" class="btn-cancel" :disabled="busy || !code.trim()">
                {{ t('admin.pos.cashOutLookup') }}
              </button>
            </div>
          </form>

          <dl v-if="found" class="found" data-testid="pos-cash-out-found">
            <div>
              <dt>{{ t('admin.pos.cashOutCustomer') }}</dt>
              <dd>{{ found.customer_name }}</dd>
            </div>
            <div>
              <dt>{{ t('admin.pos.cashOutAmount') }}</dt>
              <dd class="amount">{{ formatCurrency(found.amount) }}</dd>
            </div>
            <div v-if="found.expires_at">
              <dt>{{ t('admin.pos.cashOutExpires') }}</dt>
              <dd>{{ d(new Date(found.expires_at), 'time') }}</dd>
            </div>
          </dl>
          <p v-if="found" class="hint">{{ t('admin.pos.cashOutHandOver') }}</p>
        </template>

        <p v-if="error" class="result error" role="alert">{{ error }}</p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="close">{{ t('common.close') }}</button>
        <button
          v-if="found && !done"
          type="button"
          class="btn-primary"
          :disabled="busy"
          data-testid="pos-cash-out-confirm"
          @click="confirmAndRefresh"
        >
          {{ t('admin.pos.cashOutConfirm', { amount: formatCurrency(found.amount) }) }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}
.modal-card {
  width: 100%;
  max-width: 440px;
  background: var(--surface, #1e293b);
  border-radius: 16px;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}
.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
}
.modal-footer {
  justify-content: flex-end;
  gap: 10px;
  border-bottom: 0;
  border-top: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
}
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}
.modal-icon {
  font-size: 22px;
  color: var(--primary, #10b981);
}
.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #ffffff);
}
.modal-close-btn {
  background: transparent;
  border: none;
  color: var(--muted, #94a3b8);
  cursor: pointer;
  padding: 4px;
}
.modal-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  color: var(--ink, #ffffff);
  font-size: 13px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-label {
  font-size: 12px;
  font-weight: 600;
}
.code-row {
  display: flex;
  gap: 8px;
}
.form-input {
  flex: 1;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  color: var(--ink, #ffffff);
  outline: none;
}
.code-input {
  font-family: var(--font-mono, monospace);
  font-size: 18px;
  letter-spacing: 0.15em;
  text-transform: uppercase;
}
.found {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
}
.found div {
  display: flex;
  justify-content: space-between;
}
.found dt,
.hint {
  color: var(--muted, #94a3b8);
}
.found dd {
  margin: 0;
  font-weight: 700;
}
.amount {
  font-size: 18px;
  color: var(--primary, #10b981);
}
.hint {
  margin: 0;
}
.result {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
}
.result.ok {
  background: rgba(16, 185, 129, 0.15);
}
.result.error {
  background: rgba(239, 68, 68, 0.15);
  color: #fca5a5;
}
.btn-cancel,
.btn-primary {
  padding: 8px 14px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  color: var(--ink, #ffffff);
}
.btn-primary {
  border-color: transparent;
  background: var(--primary, #10b981);
  color: #ffffff;
}
.btn-cancel:disabled,
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
