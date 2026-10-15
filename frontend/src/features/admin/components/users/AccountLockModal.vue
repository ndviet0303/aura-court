<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { AdminUser } from '../../api/adminUserApi'

const props = withDefaults(
  defineProps<{ user: AdminUser | null; locking: boolean; saving?: boolean }>(),
  { saving: false },
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm', reason: string): void
}>()

const { t } = useI18n()
const reason = ref('')
const length = computed(() => reason.value.trim().length)
const valid = computed(() => length.value >= 10 && length.value <= 1000)

watch(
  () => props.user,
  () => {
    reason.value = ''
  },
)
</script>

<template>
  <div v-if="user" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="account-lock-title">
      <h3 id="account-lock-title" class="modal-title">
        {{ locking ? t('admin.users.lockTitle') : t('admin.users.unlockTitle') }}
      </h3>
      <p class="modal-text">
        {{ locking ? t('admin.users.lockHint') : t('admin.users.unlockHint') }}
        <strong>{{ user.email }}</strong>
      </p>
      <label class="form-label" for="account-lock-reason">{{ t('admin.users.reasonLabel') }}</label>
      <textarea
        id="account-lock-reason"
        v-model="reason"
        class="form-input"
        rows="4"
        maxlength="1000"
        data-testid="lock-reason"
        :placeholder="t('admin.users.reasonPlaceholder')"
      ></textarea>
      <span class="reason-counter" :class="{ invalid: length > 0 && !valid }">
        {{ length }}/1000
      </span>
      <div class="modal-actions">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          :variant="locking ? 'danger' : 'primary'"
          :disabled="!valid || saving"
          data-testid="confirm-lock"
          @click="emit('confirm', reason.trim())"
        >
          {{ locking ? t('admin.users.lockBtn') : t('admin.users.unlockBtn') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  max-width: 480px;
  width: 100%;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  color: var(--ink);
}

.modal-title {
  margin: 0;
}

.modal-text {
  margin: 0;
  color: var(--muted);
}

.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
  color: var(--ink);
  font: inherit;
  resize: vertical;
}

.reason-counter {
  align-self: flex-end;
  font-size: 12px;
  color: var(--muted);
}

.reason-counter.invalid {
  color: #dc2626;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
