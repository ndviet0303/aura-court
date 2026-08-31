<script setup lang="ts">
const amenities = [
  { icon: 'local_parking', name: 'Bãi Đỗ Xe Rộng Rãi', desc: 'Có chỗ đỗ ô tô & xe máy an ninh' },
  { icon: 'wifi', name: 'Wifi 6 Tốc Độ Cao', desc: 'Livestream thi đấu mượt mà' },
  { icon: 'shower', name: 'Phòng Thay Đồ & Tắm', desc: 'Có bình nóng lạnh và máy sấy' },
  { icon: 'local_cafe', name: 'Quầy Nước & Đồ Ăn Nhẹ', desc: 'Phục vụ điện giải, sinh tố' },
  { icon: 'sports_tennis', name: 'Cửa Hàng & Căng Vợt', desc: 'Căng cước 4 nút điện tử chuẩn' },
  { icon: 'weekend', name: 'Khu Ghế Chờ Khán Giả', desc: 'Ghế bọc đệm êm ái thoáng mát' },
]
</script>

<template>
  <div class="court-amenities-section">
    <h3 class="section-title">
      <span class="material-symbols-outlined title-icon">room_service</span>
      <span>Tiện Ích & Dịch Vụ Đi Kèm</span>
    </h3>
    <div class="amenities-grid">
      <div v-for="a in amenities" :key="a.name" class="amenity-card">
        <span class="material-symbols-outlined a-icon">{{ a.icon }}</span>
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
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.title-icon {
  font-size: 18px;
  color: var(--primary);
}

.amenities-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.amenity-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition: border-color 0.15s ease;
}

.amenity-card:hover {
  border-color: var(--accent);
}

.a-icon {
  font-size: 22px;
  color: var(--accent);
}

.a-name {
  font-size: 13px;
  color: var(--ink);
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
