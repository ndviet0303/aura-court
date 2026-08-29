<script setup lang="ts">
defineProps<{
  search: string
  venue: string
  venueOptions: string[]
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:venue', val: string): void
}>()
</script>

<template>
  <div class="courts-filter-bar">
    <div class="search-box">
      <input
        :value="search"
        type="text"
        class="search-input"
        placeholder="Tìm sân theo tên, cơ sở hoặc khu vực..."
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <div class="selects-box">
      <select
        :value="venue"
        class="venue-select"
        @change="emit('update:venue', ($event.target as HTMLSelectElement).value)"
      >
        <option value="all">Tất cả cụm sân Aura</option>
        <option v-for="v in venueOptions.filter(x => x !== 'all')" :key="v" :value="v">
          {{ v }}
        </option>
      </select>
    </div>
  </div>
</template>

<style scoped>
.courts-filter-bar {
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

.search-box {
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

.venue-select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 13px;
}
</style>
