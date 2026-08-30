<script setup lang="ts">
import type { MembershipPlan } from '../../types/membership'

defineProps<{
  plans: MembershipPlan[]
}>()

const emit = defineEmits<{
  (e: 'edit-plan', plan: MembershipPlan): void
  (e: 'manage-version', plan: MembershipPlan): void
}>()

function formatVnd(val: number) {
  return new Intl.NumberFormat('vi-VN').format(val) + 'đ'
}

function getCycleLabel(cycle: string) {
  if (cycle === 'quarterly') return '/ quý'
  if (cycle === 'yearly') return '/ năm'
  return '/ tháng'
}
</script>

<template>
  <section class="membership-plans-section">
    <div class="section-header">
      <div class="title-with-icon">
        <span class="icon-star-badge material-symbols-outlined">star</span>
        <h2 class="section-title">Gói Hội Viên Hiện Tại</h2>
      </div>
      <span class="plans-count-badge">{{ plans.length }} Gói đang hoạt động</span>
    </div>

    <div class="plans-grid">
      <div
        v-for="plan in plans"
        :key="plan.id"
        class="plan-card"
        :class="{ 'is-popular': plan.isPopular }"
      >
        <!-- Top accent line -->
        <div
          class="accent-line"
          :class="{ 'accent-primary': plan.isPopular, 'accent-subtle': !plan.isPopular }"
        ></div>

        <!-- Ambient blur glow for popular / VIP tier -->
        <div v-if="plan.isPopular" class="ambient-glow"></div>

        <!-- Card Header -->
        <div class="plan-card-header">
          <div class="plan-title-block">
            <div class="plan-name-row">
              <h3 class="plan-name">{{ plan.name }}</h3>
              <span
                class="version-tag"
                :class="{ 'version-gold': plan.isPopular, 'version-neutral': !plan.isPopular }"
              >
                {{ plan.version }} - {{ plan.status === 'active' ? 'Active' : 'Archived' }}
              </span>
            </div>
            <p class="plan-desc">{{ plan.description }}</p>
          </div>

          <div
            class="plan-badge-icon"
            :class="{ 'badge-gold': plan.isPopular, 'badge-neutral': !plan.isPopular }"
          >
            <span class="material-symbols-outlined">
              {{ plan.isPopular ? 'workspace_premium' : 'military_tech' }}
            </span>
          </div>
        </div>

        <!-- Price block -->
        <div class="plan-price-block">
          <span class="price-value">{{ formatVnd(plan.price) }}</span>
          <span class="price-cycle">{{ getCycleLabel(plan.billingCycle) }}</span>
        </div>

        <!-- Perks list -->
        <ul class="plan-perks-list">
          <li v-for="(perk, idx) in plan.perks" :key="idx" class="perk-item">
            <span class="material-symbols-outlined perk-check">check_circle</span>
            <span class="perk-text" :class="{ 'perk-highlight': plan.isPopular && idx === 0 }">{{ perk }}</span>
          </li>
        </ul>

        <!-- Card Footer Actions -->
        <div class="plan-card-footer">
          <button
            type="button"
            class="btn-manage-version"
            title="Quản lý phiên bản gói"
            @click="emit('manage-version', plan)"
          >
            Manage Version
          </button>
          <button
            type="button"
            class="btn-edit-plan"
            title="Chỉnh sửa chi tiết gói"
            @click="emit('edit-plan', plan)"
          >
            Edit Plan
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.membership-plans-section {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.icon-star-badge {
  font-size: 20px;
  padding: 8px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  font-variation-settings: 'FILL' 1;
}

.section-title {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  letter-spacing: -0.01em;
}

:global(html.dark) .section-title {
  color: #ffffff;
}

.plans-count-badge {
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 8px;
  background: var(--surface-container-high, #e7e8e9);
  color: var(--on-surface-variant, #4f4446);
}

:global(html.dark) .plans-count-badge {
  background: #2b2e36;
  color: #d1d5db;
}

.plans-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.plan-card {
  position: relative;
  border-radius: 20px;
  padding: 24px;
  background: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--outline-variant, #e1e3e4);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

:global(html.dark) .plan-card {
  background: #1e2025;
  border-color: #353942;
}

.plan-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.07);
}

.plan-card.is-popular {
  border-color: var(--primary-fixed, #ffd9e2);
  box-shadow: 0 6px 20px rgba(120, 85, 94, 0.08);
}

:global(html.dark) .plan-card.is-popular {
  border-color: #78555e;
}

.accent-line {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.accent-line.accent-primary {
  background: var(--primary, #78555e);
}

.accent-line.accent-subtle {
  background: var(--surface-container-high, #e7e8e9);
}

.ambient-glow {
  position: absolute;
  top: -24px;
  right: -24px;
  width: 120px;
  height: 120px;
  border-radius: 9999px;
  background: var(--primary-fixed, #ffd9e2);
  filter: blur(32px);
  opacity: 0.6;
  pointer-events: none;
}

.plan-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  position: relative;
  z-index: 2;
}

.plan-name-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}

.plan-name {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .plan-name {
  color: #ffffff;
}

.version-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

.version-gold {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
}

.version-neutral {
  background: var(--surface-container, #edeeef);
  color: var(--on-surface-variant, #4f4446);
}

:global(html.dark) .version-neutral {
  background: #2b2e36;
  color: #c0c4cc;
}

.plan-desc {
  margin: 0;
  font-size: 13px;
  color: var(--on-surface-variant, #4f4446);
}

.plan-badge-icon {
  width: 42px;
  height: 42px;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.badge-gold {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
}

.badge-neutral {
  background: var(--surface-container, #edeeef);
  color: var(--on-surface-variant, #4f4446);
}

:global(html.dark) .badge-neutral {
  background: #2b2e36;
  color: #d1d5db;
}

.plan-price-block {
  margin-bottom: 22px;
  display: flex;
  align-items: baseline;
  gap: 6px;
  position: relative;
  z-index: 2;
}

.price-value {
  font-size: 26px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  letter-spacing: -0.02em;
}

:global(html.dark) .price-value {
  color: #ffffff;
}

.price-cycle {
  font-size: 14px;
  color: var(--on-surface-variant, #4f4446);
}

.plan-perks-list {
  list-style: none;
  padding: 0;
  margin: 0 0 28px 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  flex: 1;
  position: relative;
  z-index: 2;
}

.perk-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.perk-check {
  font-size: 18px;
  color: var(--primary, #78555e);
  margin-top: 1px;
  flex-shrink: 0;
  font-variation-settings: 'FILL' 1;
}

.perk-text {
  font-size: 14px;
  color: var(--on-surface, #191c1d);
  line-height: 1.4;
}

:global(html.dark) .perk-text {
  color: #e2e8f0;
}

.perk-highlight {
  font-weight: 600;
  color: var(--primary, #78555e);
}

:global(html.dark) .perk-highlight {
  color: #f8b4c4;
}

.plan-card-footer {
  display: flex;
  gap: 10px;
  padding-top: 16px;
  border-top: 1px solid var(--surface-container, #edeeef);
  position: relative;
  z-index: 2;
}

:global(html.dark) .plan-card-footer {
  border-color: #353942;
}

.btn-manage-version {
  flex: 1;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  background: var(--surface-container-low, #f3f4f5);
  color: var(--on-surface, #191c1d);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

:global(html.dark) .btn-manage-version {
  background: #2b2e36;
  border-color: #454747;
  color: #e2e8f0;
}

.btn-manage-version:hover {
  background: var(--surface-container-high, #e7e8e9);
}

.btn-edit-plan {
  flex: 1;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-edit-plan:hover {
  background: var(--primary, #78555e);
  color: #ffffff;
}

@media (max-width: 900px) {
  .plans-grid {
    grid-template-columns: 1fr;
  }
}
</style>
