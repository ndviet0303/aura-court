<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Draft } from '../types/pos'

defineProps<{
  show: boolean
  drafts: Draft[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'loadDraft', draft: Draft): void
  (e: 'deleteDraft', id: string): void
}>()

const { t } = useI18n()

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined modal-icon">folder_open</span>
          <h3 class="modal-title">{{ t('admin.pos.draftsModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <div v-if="drafts.length === 0" class="empty-drafts-box">
          <span class="material-symbols-outlined empty-icon">bookmark_border</span>
          <h4>{{ t('admin.pos.emptyDraftsTitle') }}</h4>
          <p>{{ t('admin.pos.emptyDraftsSub') }}</p>
        </div>

        <div v-else class="drafts-list">
          <div v-for="draft in drafts" :key="draft.id" class="draft-item-card">
            <div class="draft-info">
              <div class="draft-title-row">
                <span class="draft-id-tag">{{ draft.id }}</span>
                <strong class="draft-name">{{ draft.name }}</strong>
              </div>
              <div class="draft-meta-row">
                <span class="draft-time">{{ draft.saved_at }}</span>
                <span class="draft-items-count">{{
                  t('admin.pos.draftItemsCount', { count: draft.items.length })
                }}</span>
                <strong class="draft-total">{{ formatMoney(draft.total_amount) }}</strong>
              </div>
            </div>

            <div class="draft-actions">
              <button
                type="button"
                class="btn-delete"
                :title="t('admin.pos.deleteDraft')"
                @click="emit('deleteDraft', draft.id)"
              >
                <span class="material-symbols-outlined icon-18">delete</span>
              </button>
              <button type="button" class="btn-restore" @click="emit('loadDraft', draft)">
                <span class="material-symbols-outlined icon-18">restore</span>
                <span>{{ t('admin.pos.restoreDraft') }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('admin.pos.cancel') }}
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
  max-width: 520px;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e2e8f0);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  color: var(--ink, #0f172a);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  color: var(--primary, #10b981);
  font-size: 22px;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.modal-close-btn {
  background: transparent;
  border: none;
  color: var(--muted, #64748b);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.modal-body {
  padding: 16px 18px;
  max-height: 60vh;
  overflow-y: auto;
}

.empty-drafts-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 10px;
  text-align: center;
  color: var(--muted, #64748b);
}

.empty-icon {
  font-size: 40px;
  margin-bottom: 8px;
  opacity: 0.4;
}

.empty-drafts-box h4 {
  margin: 0 0 4px;
  color: var(--ink, #0f172a);
  font-size: 14px;
}

.empty-drafts-box p {
  margin: 0;
  font-size: 12px;
}

.drafts-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.draft-item-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  background: var(--surface-hover, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 10px;
  transition: all 0.2s ease;
}

.draft-item-card:hover {
  background: rgba(16, 185, 129, 0.04);
  border-color: var(--primary, #10b981);
}

.draft-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.draft-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.draft-id-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: #e2e8f0;
  color: #475569;
}

.draft-name {
  font-size: 13px;
  color: var(--ink, #0f172a);
}

.draft-meta-row {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 11px;
  color: var(--muted, #64748b);
}

.draft-total {
  color: var(--primary, #10b981);
  font-weight: 700;
}

.draft-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-delete {
  padding: 6px;
  border-radius: 6px;
  background: transparent;
  border: 1px solid var(--outline, #cbd5e1);
  color: #ef4444;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.btn-delete:hover {
  background: rgba(239, 68, 68, 0.1);
  border-color: #ef4444;
}

.btn-restore {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 6px;
  background: var(--primary, #10b981);
  border: none;
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.btn-restore:hover {
  background: #059669;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 12px 18px;
  border-top: 1px solid var(--outline, #e2e8f0);
}

.btn-cancel {
  padding: 8px 14px;
  background: var(--surface-hover, rgba(0, 0, 0, 0.05));
  border: 1px solid var(--outline, #cbd5e1);
  border-radius: 8px;
  color: var(--ink, #0f172a);
  font-weight: 600;
  cursor: pointer;
}

.icon-18 {
  font-size: 18px;
}
</style>
