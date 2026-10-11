<script setup lang="ts">
import type { SocialSportType } from '../../types/social'

defineProps<{
  selectedSport: SocialSportType
}>()

const emit = defineEmits<{
  (e: 'update:selectedSport', sport: SocialSportType): void
}>()

interface SportOption {
  id: SocialSportType
  name: string
  icon: string
  badge: string
  desc: string
}

const sports: SportOption[] = [
  {
    id: 'badminton',
    name: 'Cầu Lông',
    icon: 'sports_tennis',
    badge: 'Tiêu chuẩn BWF',
    desc: 'Sàn thảm chống trượt & ánh sáng tiêu chuẩn',
  },
  {
    id: 'pickleball',
    name: 'Pickleball',
    icon: 'sports_tennis',
    badge: 'Xu Hướng Hot',
    desc: 'Sân tiêu chuẩn quốc tế, lưới chuẩn thi đấu',
  },
  {
    id: 'tennis',
    name: 'Quần Vợt (Tennis)',
    icon: 'sports_tennis',
    badge: 'Sân Cứng ITF',
    desc: 'Mặt sân Acrylic giảm chấn chuyên nghiệp',
  },
  {
    id: 'football',
    name: 'Bóng Đá Futsal',
    icon: 'sports_soccer',
    badge: '5v5 & 7v7',
    desc: 'Cỏ nhân tạo chất lượng cao, thoát nước tốt',
  },
  {
    id: 'basketball',
    name: 'Bóng Rổ',
    icon: 'sports_basketball',
    badge: '3x3 / 5x5',
    desc: 'Sàn gỗ thông & trụ kính lực thi đấu',
  },
  {
    id: 'volleyball',
    name: 'Bóng Chuyền',
    icon: 'sports_volleyball',
    badge: 'Trong Nhà',
    desc: 'Sàn đàn hồi cao su & cột lưới tiêu chuẩn',
  },
]
</script>

<template>
  <div class="sport-selector-section">
    <div class="section-head">
      <div class="section-title-wrap">
        <span class="material-symbols-outlined head-icon">sports_score</span>
        <h3 class="section-title">Chọn Bộ Môn Thể Thao</h3>
      </div>
      <span class="section-desc">Chọn môn bạn muốn tìm đối thủ hoặc lập kèo giao lưu</span>
    </div>

    <div class="sports-grid">
      <button
        v-for="s in sports"
        :key="s.id"
        type="button"
        class="sport-card"
        :class="{ active: selectedSport === s.id }"
        @click="emit('update:selectedSport', s.id)"
      >
        <div class="card-top">
          <div class="icon-box">
            <span class="material-symbols-outlined sport-icon">{{ s.icon }}</span>
          </div>
          <span class="badge-pill">{{ s.badge }}</span>
        </div>

        <div class="card-content">
          <strong class="sport-name">{{ s.name }}</strong>
          <span class="sport-desc">{{ s.desc }}</span>
        </div>

        <div class="card-check">
          <span class="material-symbols-outlined check-icon">
            {{ selectedSport === s.id ? 'check_circle' : 'radio_button_unchecked' }}
          </span>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.sport-selector-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 22px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 18px;
  box-shadow: var(--shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.04));
}

.section-head {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.section-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.head-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.section-title {
  margin: 0;
  font-size: 15.5px;
  font-weight: 700;
  color: var(--ink);
}

.section-desc {
  font-size: 12.5px;
  color: var(--muted);
}

.sports-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}

.sport-card {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 12px;
  padding: 16px;
  border-radius: 14px;
  border: 1.5px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.015));
  text-align: left;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  overflow: hidden;
}

.sport-card:hover {
  border-color: rgba(219, 39, 119, 0.4);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.sport-card.active {
  border-color: var(--pink-dark, #db2777);
  background: var(--pink-light, rgba(236, 72, 153, 0.06));
  box-shadow:
    0 0 0 1px var(--pink-dark, #db2777),
    0 4px 16px rgba(219, 39, 119, 0.12);
}

.card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.icon-box {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--outline);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s ease;
}

.sport-card.active .icon-box {
  background: var(--pink-dark, #db2777);
}

.sport-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.sport-card.active .sport-icon {
  color: #ffffff;
}

.badge-pill {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  color: var(--muted);
}

.sport-card.active .badge-pill {
  background: rgba(219, 39, 119, 0.12);
  border-color: rgba(219, 39, 119, 0.3);
  color: var(--pink-dark, #db2777);
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sport-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.sport-desc {
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.4;
}

.card-check {
  display: flex;
  justify-content: flex-end;
}

.check-icon {
  font-size: 18px;
  color: var(--muted);
  transition: color 0.2s ease;
}

.sport-card.active .check-icon {
  color: var(--pink-dark, #db2777);
}

@media (max-width: 640px) {
  .sports-grid {
    grid-template-columns: 1fr;
  }
}
</style>
