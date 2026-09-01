<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { StaffMember } from '../../composables/useAdminStaff'

const props = withDefaults(
  defineProps<{
    show: boolean
    member: StaffMember | null
    saving?: boolean
  }>(),
  {
    saving: false,
  }
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm', memberId: number): void
}>()

const { t } = useI18n()

function handleConfirm() {
  if (!props.member) return
  emit('confirm', props.member.id)
}
</script>

<template>
  <div v-if="show && member" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon text-danger">warning</span>
          <h3 class="modal-title">{{ t('admin.staff.confirmRemoveTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <p class="warning-text">
          {{ t('admin.staff.confirmRemoveMsg', { email: member.email }) }}
        </p>

        <div class="member-target-card">
          <strong class="target-name">{{ member.name }}</strong>
          <span class="target-role">{{ member.role }} • UID: #{{ member.userId || member.id }}</span>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          variant="danger"
          :disabled="saving"
          @click="handleConfirm"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': saving }">
            {{ saving ? 'sync' : 'delete' }}
          </span>
          <span>{{ t('admin.staff.removeConfirmBtn') }}</span>
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
  backdrop-filter: blur(4px);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  max-width: 460px;
  width: 100%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 24px;
}

.text-danger {
  color: #ef4444;
}

.modal-title {
  margin: 0;
  font-size: 16.5px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: transparent;
  border: 0;
  font-size: 16px;
  color: var(--muted);
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.warning-text {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
  line-height: 1.5;
}

.member-target-card {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 16px;
  background: rgba(239, 68, 68, 0.05);
  border-radius: 12px;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.target-name {
  font-size: 14px;
  color: var(--ink);
}

.target-role {
  font-size: 12px;
  color: var(--muted);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.icon-18 {
  font-size: 18px;
}

:global(html.dark) .modal-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .modal-header,
:global(html.dark) .modal-footer {
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .modal-footer {
  background: var(--surface-subtle, #262024);
}
</style>
