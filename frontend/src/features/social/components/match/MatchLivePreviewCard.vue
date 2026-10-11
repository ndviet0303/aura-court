<script setup lang="ts">
import { computed } from 'vue'
import { AuraButton } from '@/shared/components'
import type { SocialMatch } from '../../types/social'

const props = defineProps<{
  previewMatch: SocialMatch
  submitting: boolean
  selectedFriendsCount: number
}>()

const emit = defineEmits<{
  (e: 'submit'): void
}>()

const remainingSlots = computed(() => {
  return Math.max(0, props.previewMatch.required_participants - props.previewMatch.joined_count)
})

const isFull = computed(() => {
  return props.previewMatch.joined_count >= props.previewMatch.required_participants
})

function formatMoney(amount?: number): string {
  if (!amount) return 'Miễn phí'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
}
</script>

<template>
  <aside class="live-preview-sidebar">
    <div class="preview-header">
      <div class="preview-title-row">
        <span class="material-symbols-outlined preview-ico">visibility</span>
        <h4 class="preview-title">Xem Trước Thẻ Kèo</h4>
      </div>
      <span class="preview-live-badge">Trực quan hóa</span>
    </div>

    <!-- The Live Match Card -->
    <div class="preview-card-wrapper">
      <article class="match-card-preview" :class="{ 'is-full': isFull }">
        <!-- Card Top: Sport & Status -->
        <div class="card-header">
          <div class="sport-badge">
            <span class="material-symbols-outlined sport-ico">{{ previewMatch.sport_icon }}</span>
            <span class="sport-name">{{ previewMatch.sport_name }}</span>
            <span v-if="previewMatch.game_format" class="format-dot">·</span>
            <span v-if="previewMatch.game_format" class="format-txt">{{
              previewMatch.game_format
            }}</span>
          </div>

          <div class="slots-status-badge" :class="{ full: isFull, open: !isFull }">
            <span class="material-symbols-outlined status-ico">{{
              isFull ? 'check_circle' : 'group_add'
            }}</span>
            <span>{{ isFull ? 'Đã đủ người' : `Cần thêm ${remainingSlots} người` }}</span>
          </div>
        </div>

        <!-- Card Body -->
        <div class="card-body">
          <h3 class="match-title">
            {{ previewMatch.title }}
          </h3>

          <div class="host-profile-row">
            <div class="host-avatar-box">
              <img
                v-if="previewMatch.host_avatar"
                :src="previewMatch.host_avatar"
                :alt="previewMatch.host_name"
                class="avatar-img"
              />
              <span v-else class="avatar-fallback">{{
                previewMatch.host_name.charAt(0).toUpperCase()
              }}</span>
            </div>
            <div class="host-text-col">
              <div class="host-name-row">
                <span class="host-name">{{ previewMatch.host_name }}</span>
                <span class="reputation-badge" title="Điểm uy tín thi đấu">
                  <span class="material-symbols-outlined rep-ico">verified</span>
                  {{ previewMatch.host_reputation }}%
                </span>
              </div>
              <span class="host-sub">Chủ phòng · {{ previewMatch.city }}</span>
            </div>
          </div>

          <!-- Info Details Grid -->
          <div class="match-meta-list">
            <div class="meta-item">
              <span class="material-symbols-outlined meta-ico">location_on</span>
              <span class="meta-val">
                <strong>{{ previewMatch.venue_name }}</strong>
                <span v-if="previewMatch.court_name" class="court-sub">
                  · {{ previewMatch.court_name }}</span
                >
              </span>
            </div>

            <div class="meta-item">
              <span class="material-symbols-outlined meta-ico">schedule</span>
              <span class="meta-val font-mono">{{ previewMatch.starts_at }}</span>
            </div>

            <div class="meta-item-row">
              <div class="meta-item">
                <span class="material-symbols-outlined meta-ico">signal_cellular_alt</span>
                <span class="meta-val"
                  >Level {{ previewMatch.min_skill || 1 }} - {{ previewMatch.max_skill || 5 }}</span
                >
              </div>

              <div class="meta-item">
                <span class="material-symbols-outlined meta-ico">payments</span>
                <span class="meta-val font-mono highlight">{{
                  formatMoney(previewMatch.cost_per_person)
                }}</span>
              </div>
            </div>
          </div>

          <!-- Slots Progress Visualizer -->
          <div class="slots-visualizer">
            <div class="slots-header">
              <span class="slots-label">Thành viên tham gia:</span>
              <span class="slots-count font-mono"
                >{{ previewMatch.joined_count }}/{{ previewMatch.required_participants }}</span
              >
            </div>
            <div class="slots-dots-row">
              <div
                v-for="p in previewMatch.participants"
                :key="p.id"
                class="slot-dot filled"
                :title="`${p.name} (${p.role})`"
              >
                <img v-if="p.avatar" :src="p.avatar" class="dot-avt" />
                <span v-else class="dot-initial">{{ p.name.charAt(0).toUpperCase() }}</span>
              </div>
              <div
                v-for="idx in remainingSlots"
                :key="`empty-${idx}`"
                class="slot-dot empty"
                title="Vị trí trống đang chờ bạn tham gia"
              >
                <span class="material-symbols-outlined empty-plus">add</span>
              </div>
            </div>
          </div>
        </div>
      </article>
    </div>

    <!-- Summary Checklist -->
    <div class="match-summary-card">
      <div class="summary-item">
        <span class="summary-label">Chế độ hiển thị</span>
        <span class="summary-val">{{
          previewMatch.visibility === 'public' ? 'Công khai toàn hệ thống' : 'Chỉ người được mời'
        }}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Bạn bè đã chọn mời</span>
        <span class="summary-val font-mono">{{ selectedFriendsCount }} người</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Hình thức chia tiền</span>
        <span class="summary-val">{{
          previewMatch.is_owner_hosted
            ? 'Chia đều sau buổi'
            : previewMatch.split_bill_mode === 'auto_split'
              ? 'Tự động trừ ví Aura'
              : 'Chủ phòng tự thu'
        }}</span>
      </div>
    </div>

    <!-- Submit CTA Button -->
    <div class="action-box">
      <AuraButton
        type="button"
        variant="primary"
        size="lg"
        icon="group_add"
        block
        :loading="submitting"
        @click="emit('submit')"
      >
        {{ submitting ? 'Đang khởi tạo trận đấu...' : 'Mở Kèo & Xuất Bản Lên Bảng Tin' }}
      </AuraButton>
    </div>
  </aside>
</template>

<style scoped>
.live-preview-sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: 24px;
}

.preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 4px;
}

.preview-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.preview-ico {
  font-size: 18px;
  color: var(--pink-dark, #db2777);
}

.preview-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.preview-live-badge {
  font-size: 10.5px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.1);
  padding: 2px 8px;
  border-radius: 20px;
}

.preview-card-wrapper {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 16px;
  box-shadow: var(--shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.04));
}

.match-card-preview {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.sport-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  background: var(--pink-light, rgba(236, 72, 153, 0.08));
  padding: 4px 10px;
  border-radius: 20px;
}

.sport-ico {
  font-size: 16px;
}

.format-dot {
  opacity: 0.5;
}

.slots-status-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 12px;
}

.slots-status-badge.open {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.slots-status-badge.full {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.status-ico {
  font-size: 14px;
}

.card-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.match-title {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--ink);
  line-height: 1.4;
}

.host-profile-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.host-avatar-box {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.05));
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--outline);
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-fallback {
  font-size: 13px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.host-text-col {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.host-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.host-name {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.reputation-badge {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 10px;
  font-weight: 700;
  color: #10b981;
}

.rep-ico {
  font-size: 12px;
}

.host-sub {
  font-size: 11px;
  color: var(--muted);
}

.match-meta-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border-radius: 12px;
  border: 1px solid var(--outline);
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--ink);
}

.meta-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.meta-ico {
  font-size: 15px;
  color: var(--muted);
}

.court-sub {
  font-weight: normal;
  color: var(--muted);
}

.highlight {
  color: var(--pink-dark, #db2777);
  font-weight: 700;
}

.slots-visualizer {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.slots-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11.5px;
  color: var(--muted);
  font-weight: 600;
}

.slots-dots-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.slot-dot {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  border: 1.5px solid var(--outline);
}

.slot-dot.filled {
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  border-color: var(--pink-dark, #db2777);
  overflow: hidden;
}

.dot-avt {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.slot-dot.empty {
  border-style: dashed;
  color: var(--muted);
  background: transparent;
}

.empty-plus {
  font-size: 14px;
}

.match-summary-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.summary-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
}

.summary-label {
  color: var(--muted);
}

.summary-val {
  font-weight: 700;
  color: var(--ink);
}

.action-box {
  display: flex;
  flex-direction: column;
}
</style>
