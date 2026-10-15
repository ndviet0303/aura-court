<script setup lang="ts">
const query = defineModel<string>({ required: true })
</script>

<template>
  <div class="availability-search-box">
    <span class="material-symbols-outlined icon-16 search-icon">search</span>
    <input
      v-model="query"
      type="text"
      class="court-search-input"
      placeholder="Tìm sân, khu vực..."
      aria-label="Tìm sân hoặc khu vực"
    />
    <button
      v-if="query"
      type="button"
      class="search-clear-btn"
      aria-label="Xóa tìm kiếm"
      @click="query = ''"
    >
      ×
    </button>
  </div>
</template>

<style scoped>
.availability-search-box {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 34px;
  box-sizing: border-box;
  padding: 0 12px;
  border-radius: 999px;
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  transition: border-color 0.2s ease;
}

.availability-search-box:focus-within {
  border-color: var(--primary);
}

.search-icon {
  font-size: 15px;
  color: var(--muted);
}

.court-search-input {
  border: none;
  background: transparent;
  font-size: 12px;
  color: var(--ink);
  outline: none;
  width: 110px;
}

.search-clear-btn {
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  font-size: 15px;
  line-height: 1;
  padding: 0;
}
</style>
