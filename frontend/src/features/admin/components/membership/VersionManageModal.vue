<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { MembershipPlan } from '../../types/membership'

const props = defineProps<{
  show: boolean
  plan: MembershipPlan | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'archive-plan', planId: string | number): void
}>()

const { t } = useI18n()

function handleArchive() {
  if (!props.plan) return
  if (
    window.confirm(
      t('admin.membership.archivePlanConfirm', { name: resolveLocalizedText(props.plan.name) }),
    )
  ) {
    emit('archive-plan', props.plan.id)
    emit('close')
  }
}
</script>

<template>
  <div v-if="show && plan" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-dialog-sm" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div class="header-left">
          <div class="modal-icon-badge">
            <span class="material-symbols-outlined">published_with_changes</span>
          </div>
          <div>
            <h3 class="modal-title">
              {{
                t('admin.membership.versionManageTitle', { name: resolveLocalizedText(plan.name) })
              }}
            </h3>
            <span class="version-tag"
              >{{ plan.version }} •
              {{
                plan.status === 'active'
                  ? t('admin.membership.activeStatus')
                  : t('admin.membership.archivedStatus')
              }}</span
            >
          </div>
        </div>
        <button type="button" class="btn-close-circle" @click="emit('close')">
          <span class="material-symbols-outlined icon-18">close</span>
        </button>
      </div>

      <div class="modal-body custom-scroll">
        <div class="stats-card-grid">
          <div class="stat-box">
            <span class="stat-lbl">{{ t('admin.membership.activeSubscribersLabel') }}</span>
            <strong class="stat-val text-pink">{{
              t('admin.membership.activeSubscribersValue', { count: plan.activeSubscribers })
            }}</strong>
          </div>
          <div class="stat-box">
            <span class="stat-lbl">{{ t('admin.membership.retentionRateLabel') }}</span>
            <strong class="stat-val text-green">94.8%</strong>
          </div>
        </div>

        <div class="notice-callout">
          <span class="material-symbols-outlined icon-20 text-pink">verified_user</span>
          <p class="notice-txt">
            {{ t('admin.membership.versionProtectNotice', { count: plan.activeSubscribers }) }}
          </p>
        </div>

        <div class="snapshot-card">
          <h4 class="snapshot-head">
            {{ t('admin.membership.currentPerksSnapshotTitle', { version: plan.version }) }}
          </h4>
          <ul class="snapshot-perks">
            <li v-for="(p, i) in plan.perks" :key="i" class="snap-item">
              <span class="material-symbols-outlined icon-16 text-pink">check_circle</span>
              <span>{{ resolveLocalizedText(p) }}</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton type="button" variant="secondary" @click="emit('close')">{{
          t('admin.membership.closeBtn')
        }}</AuraButton>
        <AuraButton
          v-if="plan.status !== 'archived'"
          type="button"
          variant="danger"
          @click="handleArchive"
        >
          {{ t('admin.membership.archivePlanBtn') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-dialog-sm {
  background: var(--surface);
  border-radius: 24px;
  width: 100%;
  max-width: 520px;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
  border: 1px solid var(--outline);
  overflow: hidden;
}
:global(html.dark) .modal-dialog-sm {
  background: #16181f;
  border-color: #2b2f3a;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline);
  background: rgba(219, 39, 119, 0.03);
  flex-shrink: 0;
}
:global(html.dark) .modal-header {
  border-bottom-color: #232732;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.modal-icon-badge {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.12);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}
.modal-title {
  margin: 0 0 3px;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}
.version-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  background: rgba(219, 39, 119, 0.12);
  color: var(--pink-dark, #db2777);
}
:global(html.dark) .version-tag {
  color: #f472b6;
}
.btn-close-circle {
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-close-circle:hover {
  color: var(--ink);
  border-color: var(--ink);
}
.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.stats-card-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.stat-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-container-high, #f8f9fa);
  border: 1px solid var(--outline);
}
:global(html.dark) .stat-box {
  background: #1c1f27;
  border-color: #282c37;
}
.stat-lbl {
  font-size: 11px;
  color: var(--muted);
  font-weight: 600;
}
.stat-val {
  font-size: 15px;
  font-weight: 800;
}
.notice-callout {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.08);
  border: 1px solid rgba(219, 39, 119, 0.22);
}
.notice-txt {
  margin: 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--ink);
}
:global(html.dark) .notice-txt {
  color: #e5e7eb;
}
.snapshot-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-container-high, #f8f9fa);
  border: 1px solid var(--outline);
}
:global(html.dark) .snapshot-card {
  background: #1c1f27;
  border-color: #282c37;
}
.snapshot-head {
  margin: 0;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}
.snapshot-perks {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.snap-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--muted);
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface);
  flex-shrink: 0;
}
:global(html.dark) .modal-footer {
  background: #16181f;
  border-top-color: #232732;
}
.text-pink {
  color: var(--pink-dark, #db2777);
}
.text-green {
  color: #10b981;
}
.icon-20 {
  font-size: 20px;
}
.icon-18 {
  font-size: 18px;
}
.icon-16 {
  font-size: 16px;
}
</style>
