<script setup lang="ts">
defineProps<{
  activeTab: 'all' | 'open' | 'ongoing' | 'completed' | 'my'
  searchQuery: string
  selectedSport: string
  selectedFormat: string
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'all' | 'open' | 'ongoing' | 'completed' | 'my'): void
  (e: 'update:searchQuery', q: string): void
  (e: 'update:selectedSport', sport: string): void
  (e: 'update:selectedFormat', format: string): void
}>()
</script>

<template>
  <div class="tournament-filters-wrap">
    <!-- Status Tabs -->
    <div class="status-tabs-row">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'all' }"
        @click="emit('update:activeTab', 'all')"
      >
        Tất Cả Giải
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'open' }"
        @click="emit('update:activeTab', 'open')"
      >
        🔥 Đang Mở Đăng Ký
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'ongoing' }"
        @click="emit('update:activeTab', 'ongoing')"
      >
        ⚡ Đang Tranh Tài
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'completed' }"
        @click="emit('update:activeTab', 'completed')"
      >
        🏆 Đã Kết Thúc
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'my' }"
        @click="emit('update:activeTab', 'my')"
      >
        👤 Giải Của Tôi
      </button>
    </div>

    <!-- Filter Inputs Row -->
    <div class="inputs-row">
      <div class="search-input-wrap">
        <input
          :value="searchQuery"
          type="text"
          class="search-input"
          placeholder="Tìm giải theo tên, địa điểm, hạng đấu..."
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <select
        :value="selectedSport"
        class="select-filter"
        @change="emit('update:selectedSport', ($event.target as HTMLSelectElement).value)"
      >
        <option value="all">Mọi bộ môn</option>
        <option value="badminton">Cầu lông</option>
        <option value="pickleball">Pickleball</option>
        <option value="tennis">Tennis</option>
      </select>

      <select
        :value="selectedFormat"
        class="select-filter"
        @change="emit('update:selectedFormat', ($event.target as HTMLSelectElement).value)"
      >
        <option value="all">Mọi thể thức</option>
        <option value="single_elimination">Loại trực tiếp (Knockout)</option>
        <option value="round_robin">Vòng tròn tính điểm</option>
        <option value="double_elimination">Nhánh thắng - thua (Double)</option>
      </select>
    </div>
  </div>
</template>

<style scoped>
.tournament-filters-wrap {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.status-tabs-row {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.tab-btn {
  padding: 8px 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.tab-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.inputs-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.search-input-wrap {
  flex: 1;
  min-width: 260px;
}

.search-input, .select-filter {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.search-input {
  width: 100%;
}
</style>
