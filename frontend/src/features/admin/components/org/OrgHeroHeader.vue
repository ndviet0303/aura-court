<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import SelectDropdown, { type DropdownOption } from '@/shared/components/forms/SelectDropdown.vue'
import type { Organization } from '../../composables/useOrganizationOwner'

const props = defineProps<{
  org: Organization
  organizationsList?: Organization[]
  activeTab: 'profile' | 'venues' | 'members' | 'policies' | 'summary'
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'profile' | 'venues' | 'members' | 'policies' | 'summary'): void
  (e: 'switchOrg', orgId: number): void
}>()

const { t } = useI18n()

const orgDropdownOptions = computed<DropdownOption[]>(() => {
  if (!props.organizationsList || props.organizationsList.length === 0) {
    return [{ value: props.org.id, label: resolveLocalizedText(props.org.name) || 'Aura Court' }]
  }
  return props.organizationsList.map((o) => ({
    value: o.id,
    label: resolveLocalizedText(o.name) || `Tổ chức #${o.id}`,
    sublabel: `ID: ${o.id} · ${o.status || 'active'}`,
    icon: 'domain',
  }))
})
</script>

<template>
  <div class="org-hero-card" :style="{ '--org-brand': org.brand_color || '#6B2D3F' }">
    <!-- Top row: Identity & Quick Switcher -->
    <div class="org-main-row">
      <div class="org-avatar-brand">
        <img v-if="org.logo_url" :src="org.logo_url" :alt="org.name" class="org-logo-img" />
        <div v-else class="org-logo-fallback">
          <span class="material-symbols-outlined org-icon-main">corporate_fare</span>
        </div>
      </div>

      <div class="org-meta">
        <div class="title-row">
          <h1 class="org-name">{{ resolveLocalizedText(org.name) || t('admin.org.pageTitle') }}</h1>
          <span
            class="status-pill font-mono"
            :class="org.status === 'active' ? 'status-active' : 'status-inactive'"
          >
            ● {{ (org.status || 'ACTIVE').toUpperCase() }}
          </span>
          <span class="slug-tag font-mono">@{{ org.slug || 'aura-court' }}</span>
        </div>

        <div class="org-chips-row">
          <span v-if="org.tax_code" class="meta-chip font-mono">
            <span class="material-symbols-outlined icon-14">badge</span>
            MST: {{ org.tax_code }}
          </span>
          <span v-if="org.phone" class="meta-chip font-mono">
            <span class="material-symbols-outlined icon-14">call</span>
            {{ org.phone }}
          </span>
          <span v-if="org.support_email" class="meta-chip font-mono">
            <span class="material-symbols-outlined icon-14">mail</span>
            {{ org.support_email }}
          </span>
          <span v-if="org.address" class="meta-chip address-chip">
            <span class="material-symbols-outlined icon-14">pin_drop</span>
            {{ org.address }}
          </span>
        </div>
      </div>

      <!-- Right side: Quick Org Switcher if multiple orgs -->
      <div v-if="organizationsList && organizationsList.length > 1" class="org-switcher-wrapper">
        <span class="switcher-label">{{ t('admin.org.switchOrgLabel') }}</span>
        <div class="switcher-dropdown-container">
          <SelectDropdown
            :model-value="org.id"
            :options="orgDropdownOptions"
            variant="bordered"
            @update:model-value="emit('switchOrg', Number($event))"
          />
        </div>
      </div>
    </div>

    <!-- Tabs Navigation -->
    <nav class="org-tabs-bar" aria-label="Organization Navigation">
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'profile' }"
        @click="emit('update:activeTab', 'profile')"
      >
        <span class="material-symbols-outlined icon-18">badge</span>
        <span>{{ t('admin.org.tabProfile') }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'venues' }"
        @click="emit('update:activeTab', 'venues')"
      >
        <span class="material-symbols-outlined icon-18">location_on</span>
        <span>{{ t('admin.org.tabVenues', { count: org.venues?.length || 0 }) }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'members' }"
        @click="emit('update:activeTab', 'members')"
      >
        <span class="material-symbols-outlined icon-18">group</span>
        <span>{{ t('admin.org.tabMembers', { count: org.members?.length || 0 }) }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'policies' }"
        @click="emit('update:activeTab', 'policies')"
      >
        <span class="material-symbols-outlined icon-18">tune</span>
        <span>{{ t('admin.org.tabPolicies') }}</span>
      </button>
    </nav>
  </div>
</template>

<style scoped>
.org-hero-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px 28px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.org-hero-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--org-brand, var(--pink-dark, #db2777));
}

.org-main-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}

.org-avatar-brand {
  width: 64px;
  height: 64px;
  border-radius: 18px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  border: 1.5px solid var(--outline);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.org-logo-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.org-logo-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
}

.org-icon-main {
  font-size: 32px;
  color: var(--org-brand, var(--pink-dark, #db2777));
}

.org-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
  min-width: 260px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.org-name {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink);
}

.status-pill {
  font-size: 11px;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 6px;
}

.status-active {
  color: #10b981;
  background: rgba(16, 185, 129, 0.12);
}

.status-inactive {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.12);
}

.slug-tag {
  font-size: 12px;
  color: var(--muted);
  font-weight: 600;
}

.org-chips-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--muted);
  padding: 4px 10px;
  border-radius: 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
}

.address-chip {
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.org-switcher-wrapper {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 220px;
}

.switcher-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
}

.switcher-dropdown-container {
  min-width: 220px;
}

.org-tabs-bar {
  display: flex;
  gap: 8px;
  border-top: 1px solid var(--outline);
  padding-top: 18px;
  overflow-x: auto;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
  font-family: inherit;
}

.tab-btn:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  color: var(--ink);
}

.tab-btn.active {
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  border-color: rgba(219, 39, 119, 0.25);
  font-weight: 700;
}

:global(html.dark) .org-hero-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .org-avatar-brand {
  background: var(--surface-subtle, #262024);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .org-name {
  color: var(--ink, #faf7f3);
}

:global(html.dark) .slug-tag {
  color: var(--muted, #a89fa4);
}

:global(html.dark) .meta-chip {
  color: var(--ink, #faf7f3);
  background: var(--surface-subtle, #262024);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .switcher-label {
  color: var(--muted, #a89fa4);
}

:global(html.dark) .tab-btn {
  color: var(--muted, #a89fa4);
}

:global(html.dark) .tab-btn:hover {
  background: var(--soft, #30292e);
  color: #ffffff;
}

:global(html.dark) .tab-btn.active {
  background: rgba(229, 142, 159, 0.18);
  color: var(--pink-dark, #e58e9f);
  border-color: rgba(229, 142, 159, 0.35);
}

.icon-14 {
  font-size: 14px;
}

.icon-18 {
  font-size: 18px;
}
</style>
