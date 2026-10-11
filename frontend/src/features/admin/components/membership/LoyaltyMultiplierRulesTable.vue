<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, SkeletonLoader } from '@/shared/components'
import type { MultiplierRule } from '../../types/membership'

const props = defineProps<{
  rules: MultiplierRule[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'open-create-rule'): void
  (e: 'edit-rule', rule: MultiplierRule): void
  (e: 'toggle-rule', ruleId: string): void
  (e: 'delete-rule', ruleId: string): void
}>()

const { t } = useI18n()

function getTagLabel(tag: string) {
  if (tag === 'Off-peak') return t('admin.membership.tagOffPeak')
  if (tag === 'Birthday') return t('admin.membership.tagBirthday')
  if (tag === 'Tournament') return t('admin.membership.tagTournament')
  if (tag === 'Weekend') return t('admin.membership.tagWeekend')
  return t('admin.membership.tagSpecial')
}

function getTagClass(tag: string) {
  if (tag === 'Off-peak') return 'tag-amber'
  if (tag === 'Birthday') return 'tag-purple'
  if (tag === 'Tournament') return 'tag-cyan'
  if (tag === 'Weekend') return 'tag-emerald'
  return 'tag-neutral'
}

function confirmDelete(rule: MultiplierRule) {
  if (
    window.confirm(
      t('admin.membership.deleteRuleConfirm', { name: resolveLocalizedText(rule.name) }),
    )
  ) {
    emit('delete-rule', rule.id)
  }
}
</script>

<template>
  <div class="rules-card-container">
    <div class="rules-head-row">
      <div class="title-with-count">
        <h3 class="rules-card-title">{{ t('admin.membership.multiplierRulesTitle') }}</h3>
        <span v-if="!loading" class="count-chip">{{ rules.length }}</span>
      </div>
      <AuraButton v-if="!loading" variant="primary" size="sm" @click="emit('open-create-rule')">
        <span class="material-symbols-outlined icon-16">add</span>
        <span>{{ t('admin.membership.addRuleBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="rules-skeleton-box">
      <SkeletonLoader height="48px" width="100%" radius="12px" />
      <SkeletonLoader height="48px" width="100%" radius="12px" />
    </div>

    <!-- Empty State -->
    <div v-else-if="!rules.length" class="empty-rules-tile">
      <p class="empty-txt">{{ t('admin.membership.noRulesFound') }}</p>
    </div>

    <!-- Multiplier Rules Table -->
    <div v-else class="table-scroll-wrap">
      <table class="aura-rules-table">
        <thead>
          <tr>
            <th>{{ t('admin.membership.hdrRuleName') }}</th>
            <th class="text-center">{{ t('admin.membership.hdrMultiplier') }}</th>
            <th>{{ t('admin.membership.hdrTag') }}</th>
            <th class="text-center">{{ t('admin.membership.hdrStatus') }}</th>
            <th class="text-right">{{ t('admin.membership.hdrActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="rule in rules"
            :key="rule.id"
            class="rule-tr"
            :class="{ 'is-paused': !rule.isActive }"
          >
            <td class="rule-name-cell">
              <span class="rule-title-txt">{{ resolveLocalizedText(rule.name) }}</span>
              <span v-if="rule.schedule" class="rule-schedule-txt">{{
                resolveLocalizedText(rule.schedule)
              }}</span>
            </td>
            <td class="text-center">
              <span class="mult-badge">x{{ rule.multiplier }}</span>
            </td>
            <td>
              <span class="tag-badge" :class="getTagClass(rule.tag)">{{
                getTagLabel(rule.tag)
              }}</span>
            </td>
            <td class="text-center">
              <button
                type="button"
                class="aura-toggle-switch"
                :class="{ active: rule.isActive }"
                @click="emit('toggle-rule', rule.id)"
              >
                <span class="switch-ball"></span>
                <span class="switch-txt">{{
                  rule.isActive
                    ? t('admin.membership.statusActive')
                    : t('admin.membership.statusInactive')
                }}</span>
              </button>
            </td>
            <td class="text-right">
              <div class="row-btns">
                <button
                  type="button"
                  class="icon-action-btn"
                  :title="t('admin.membership.editRuleBtn')"
                  @click="emit('edit-rule', rule)"
                >
                  <span class="material-symbols-outlined icon-16">edit</span>
                </button>
                <button
                  type="button"
                  class="icon-action-btn del"
                  :title="t('admin.membership.deleteRuleBtn')"
                  @click="confirmDelete(rule)"
                >
                  <span class="material-symbols-outlined icon-16">delete</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.rules-card-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 20px;
}
:global(html.dark) .rules-card-container {
  background: #181a20;
  border-color: #2a2d36;
}
.rules-head-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.title-with-count {
  display: flex;
  align-items: center;
  gap: 8px;
}
.rules-card-title {
  margin: 0;
  font-size: 14.5px;
  font-weight: 800;
  color: var(--ink);
}
.count-chip {
  font-size: 11px;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 9999px;
  background: rgba(0, 0, 0, 0.08);
  color: var(--muted);
}
:global(html.dark) .count-chip {
  background: #252830;
}
.rules-skeleton-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.table-scroll-wrap {
  overflow-x: auto;
  border-radius: 14px;
  border: 1px solid var(--outline);
}
:global(html.dark) .table-scroll-wrap {
  border-color: #262932;
}
.aura-rules-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  background: var(--surface);
}
:global(html.dark) .aura-rules-table {
  background: #181a20;
}
.aura-rules-table th {
  padding: 10px 14px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--muted);
  background: var(--surface-container-high, #f8f9fa);
  border-bottom: 1px solid var(--outline);
  letter-spacing: 0.04em;
}
:global(html.dark) .aura-rules-table th {
  background: #1e2129;
  border-bottom-color: #262932;
}
.aura-rules-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--outline);
  color: var(--ink);
}
:global(html.dark) .aura-rules-table td {
  border-bottom-color: #22252e;
}
.rule-tr:last-child td {
  border-bottom: none;
}
.rule-tr.is-paused {
  opacity: 0.55;
}
.rule-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.rule-title-txt {
  font-size: 13.5px;
  font-weight: 700;
}
.rule-schedule-txt {
  font-size: 11px;
  color: var(--muted);
}
.mult-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 6px;
  background: rgba(245, 158, 11, 0.15);
  color: #d97706;
  font-weight: 800;
  font-size: 12px;
}
:global(html.dark) .mult-badge {
  color: #fbbf24;
}
.tag-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}
.tag-amber {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
}
.tag-purple {
  background: rgba(139, 92, 246, 0.12);
  color: #8b5cf6;
}
.tag-cyan {
  background: rgba(6, 182, 212, 0.12);
  color: #0284c7;
}
.tag-emerald {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
}
.tag-neutral {
  background: var(--surface-container-high, #e5e7eb);
  color: var(--muted);
}
.aura-toggle-switch {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  border-radius: 9999px;
  border: 1px solid var(--outline);
  background: transparent;
  cursor: pointer;
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
}
.switch-ball {
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  background: var(--muted);
}
.aura-toggle-switch.active {
  border-color: #10b981;
  color: #10b981;
  background: rgba(16, 185, 129, 0.08);
}
.aura-toggle-switch.active .switch-ball {
  background: #10b981;
}
.row-btns {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}
.icon-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--muted);
  cursor: pointer;
}
.icon-action-btn:hover {
  color: var(--ink);
  border-color: var(--ink);
}
.icon-action-btn.del:hover {
  color: #ef4444;
  border-color: #ef4444;
}
.empty-rules-tile {
  padding: 24px;
  text-align: center;
  border: 1px dashed var(--outline);
  border-radius: 12px;
}
.empty-txt {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}
.text-center {
  text-align: center;
}
.text-right {
  text-align: right;
}
.icon-16 {
  font-size: 16px;
}
</style>
