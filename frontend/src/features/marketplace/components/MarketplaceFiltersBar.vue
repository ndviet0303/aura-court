<script setup lang="ts">
defineProps<{
  search: string
  timePeriod: string
  venue: string
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:timePeriod', val: string): void
  (e: 'update:venue', val: string): void
}>()
</script>

<template>
  <div class="filters-bar-card">
    <div class="search-wrap">
      <input
        :value="search"
        type="text"
        class="search-input"
        placeholder="Tìm sân, khu vực, tên người pass..."
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <div class="selects-row">
      <select
        :value="timePeriod"
        class="filter-select"
        @change="emit('update:timePeriod', ($event.target as HTMLSelectElement).value)"
      >
        <option value="all">Tất cả khung giờ</option>
        <option value="morning">Sáng (06:00 - 12:00)</option>
        <option value="afternoon">Chiều (12:00 - 17:00)</option>
        <option value="evening">Tối giờ vàng (17:00 - 22:00)</option>
      </select>

      <select
        :value="venue"
        class="filter-select"
        @change="emit('update:venue', ($event.target as HTMLSelectElement).value)"
      >
        <option value="all">Tất cả cụm sân Aura</option>
        <option value="Quận 1">Aura Center (Quận 1)</option>
        <option value="Quận 2">Aura East (Quận 2)</option>
        <option value="Phú Nhuận">Aura North (Phú Nhuận)</option>
      </select>
    </div>
  </div>
</template>

<style scoped>
.filters-bar-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 14px 18px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.search-wrap {
  flex: 1;
  min-width: 240px;
}

.search-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 13px;
}

.selects-row {
  display: flex;
  gap: 10px;
}

.filter-select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 13px;
}
</style>
