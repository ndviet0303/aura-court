<script setup lang="ts">
import { AuraButton } from '@/shared/components'

defineProps<{
  activeTab: 'create' | 'series' | 'invoices'
  seriesCount: number
  invoicesCount: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'create' | 'series' | 'invoices'): void
  (e: 'refresh'): void
}>()
</script>

<template>
  <section class="recurring-hero">
    <div class="hero-content">
      <div class="category-eyebrow">
        <span class="material-symbols-outlined eyebrow-icon">event_repeat</span>
        <span class="font-mono">RECURRING CONTRACTS & SERIES</span>
      </div>

      <h1 class="hero-title">Đặt Sân Cố Định & Quản Lý Lịch Định Kỳ</h1>
      <p class="hero-sub">
        Đăng ký giữ khung giờ cố định hàng tuần cho cá nhân, câu lạc bộ hoặc doanh nghiệp. Tự động
        giữ chỗ, xuất hóa đơn theo chu kỳ tháng và đối soát lịch minh bạch 100%.
      </p>

      <div class="hero-badges">
        <div class="trust-pill">
          <span class="material-symbols-outlined icon-trust">verified</span>
          <span>Giữ khung giờ cố định 100%</span>
        </div>
        <div class="trust-pill">
          <span class="material-symbols-outlined icon-trust">payments</span>
          <span>Hóa đơn chu kỳ linh hoạt</span>
        </div>
        <div class="trust-pill">
          <span class="material-symbols-outlined icon-trust">sync</span>
          <span>Đồng bộ lịch thi đấu</span>
        </div>
      </div>
    </div>

    <div class="hero-tabs-wrap">
      <div class="tab-switcher" role="tablist">
        <button
          type="button"
          role="tab"
          class="tab-btn"
          :class="{ active: activeTab === 'create' }"
          :aria-selected="activeTab === 'create'"
          @click="emit('update:activeTab', 'create')"
        >
          <span class="material-symbols-outlined tab-icon">add_circle</span>
          <span>Tạo Lịch Cố Định Mới</span>
        </button>

        <button
          type="button"
          role="tab"
          class="tab-btn"
          :class="{ active: activeTab === 'series' }"
          :aria-selected="activeTab === 'series'"
          @click="emit('update:activeTab', 'series')"
        >
          <span class="material-symbols-outlined tab-icon">event_repeat</span>
          <span>Hợp Đồng Định Kỳ</span>
          <span v-if="seriesCount > 0" class="tab-count-badge">{{ seriesCount }}</span>
        </button>

        <button
          type="button"
          role="tab"
          class="tab-btn"
          :class="{ active: activeTab === 'invoices' }"
          :aria-selected="activeTab === 'invoices'"
          @click="emit('update:activeTab', 'invoices')"
        >
          <span class="material-symbols-outlined tab-icon">receipt_long</span>
          <span>Hóa Đơn Chu Kỳ Tháng</span>
          <span v-if="invoicesCount > 0" class="tab-count-badge gold">{{ invoicesCount }}</span>
        </button>
      </div>

      <AuraButton
        variant="ghost"
        size="sm"
        icon="refresh"
        :loading="loading"
        title="Làm mới dữ liệu"
        @click="emit('refresh')"
      />
    </div>
  </section>
</template>

<style scoped>
.recurring-hero {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px 24px 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
  position: relative;
  overflow: hidden;
}

.recurring-hero::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--pink-dark, #db2777), #f59e0b, #10b981);
}

.hero-content {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.category-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--pink-dark, #db2777);
}

.eyebrow-icon {
  font-size: 16px;
}

.hero-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.02em;
}

.hero-sub {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  color: var(--muted);
  max-width: 800px;
}

.hero-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 4px;
}

.trust-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
}

.icon-trust {
  font-size: 15px;
  color: var(--pink-dark, #db2777);
}

.hero-tabs-wrap {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid var(--outline);
  flex-wrap: wrap;
}

.tab-switcher {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tab-btn:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  color: var(--ink);
}

.tab-btn.active {
  background: var(--pink-dark, #db2777);
  border-color: var(--pink-dark, #db2777);
  color: #fff;
  box-shadow: 0 4px 12px rgba(219, 39, 119, 0.25);
}

.tab-icon {
  font-size: 18px;
}

.tab-count-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(255, 255, 255, 0.25);
  color: #fff;
}

.tab-btn:not(.active) .tab-count-badge {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.08));
  color: var(--ink);
}

.tab-count-badge.gold {
  background: #f59e0b;
  color: #fff;
}
</style>
