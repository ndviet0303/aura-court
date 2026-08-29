<script setup lang="ts">
const amenities = [
  { icon: '🚗', name: 'Bãi Đỗ Xe Rộng Rãi', desc: 'Có chỗ đỗ ô tô & xe máy an ninh' },
  { icon: '📶', name: 'Wifi 6 Tốc Độ Cao', desc: 'Livestream thi đấu mượt mà' },
  { icon: '🚿', name: 'Phòng Thay Đồ & Tắm', desc: 'Có bình nóng lạnh và máy sấy' },
  { icon: '☕', name: 'Quầy Nước & Đồ Ăn Nhẹ', desc: 'Phục vụ điện giải, sinh tố' },
  { icon: '🏸', name: 'Cửa Hàng & Căng Vợt', desc: 'Căng cước 4 nút điện tử chuẩn' },
  { icon: '🛋️', name: 'Khu Ghế Chờ Khán Giả', desc: 'Ghế bọc đệm êm ái thoáng mát' },
]
</script>

<template>
  <div class="court-amenities-section">
    <h3 class="section-title">✨ Tiện Ích & Dịch Vụ Đi Kèm</h3>
    <div class="amenities-grid">
      <div v-for="a in amenities" :key="a.name" class="amenity-card">
        <span class="a-icon">{{ a.icon }}</span>
        <strong class="a-name">{{ a.name }}</strong>
        <span class="a-desc">{{ a.desc }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.court-amenities-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.amenities-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.amenity-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.a-icon {
  font-size: 22px;
}

.a-name {
  font-size: 13px;
}

.a-desc {
  font-size: 11px;
  color: var(--muted);
}

@media (max-width: 768px) {
  .amenities-grid {
    grid-template-columns: 1fr;
  }
}
</style>
