<script setup lang="ts">
import type { FriendInvite } from '../../composables/useCreateMatch'

defineProps<{
  friends: FriendInvite[]
  searchQuery: string
  selectedCount: number
}>()

const emit = defineEmits<{
  (e: 'toggle', id: number): void
  (e: 'update:searchQuery', q: string): void
}>()
</script>

<template>
  <div class="invite-network-section">
    <div class="section-head">
      <div class="section-title-wrap">
        <span class="material-symbols-outlined head-icon">person_add</span>
        <h3 class="section-title">Mời Trực Tiếp Bạn Chơi & Đồng Đội</h3>
        <span v-if="selectedCount > 0" class="selected-badge">
          Đã chọn {{ selectedCount }}
        </span>
      </div>
      <span class="section-desc">Gửi lời mời trực tiếp đến bạn bè quen thuộc trong mạng lưới Aura</span>
    </div>

    <!-- Search Friends Filter -->
    <div class="search-input-box">
      <span class="material-symbols-outlined search-ico">search</span>
      <input
        :value="searchQuery"
        type="text"
        class="friend-search-input"
        placeholder="Tìm bạn theo tên hoặc trình độ..."
        @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
      />
      <button
        v-if="searchQuery"
        type="button"
        class="clear-search-btn"
        @click="emit('update:searchQuery', '')"
      >
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <!-- Friends Grid -->
    <div class="friends-grid">
      <div
        v-for="f in friends"
        :key="f.id"
        class="friend-card"
        :class="{ active: f.isSelected }"
        @click="emit('toggle', f.id)"
      >
        <div class="avt-col">
          <img v-if="f.avatar" :src="f.avatar" :alt="f.name" class="f-avt" />
          <div v-else class="f-avt-placeholder font-mono">
            {{ f.name.charAt(0).toUpperCase() }}
          </div>
          <span v-if="f.isSelected" class="selected-dot"></span>
        </div>

        <div class="f-info-col">
          <div class="f-name-row">
            <strong class="f-name">{{ f.name }}</strong>
            <span class="f-rep-badge" title="Điểm uy tín">
              <span class="material-symbols-outlined rep-ico">verified</span>
              {{ f.reputation }}%
            </span>
          </div>
          <div class="f-tags-row">
            <span class="f-skill-pill">{{ f.skill }}</span>
            <span class="f-city-txt">{{ f.city }}</span>
          </div>
        </div>

        <div class="f-action-col">
          <button
            type="button"
            class="invite-toggle-btn"
            :class="{ invited: f.isSelected }"
            @click.stop="emit('toggle', f.id)"
          >
            <span class="material-symbols-outlined btn-ico">
              {{ f.isSelected ? 'check' : 'add' }}
            </span>
            <span>{{ f.isSelected ? 'Đã mời' : 'Mời' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.invite-network-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
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
  flex-wrap: wrap;
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

.selected-badge {
  font-size: 11px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.12);
  padding: 2px 8px;
  border-radius: 20px;
}

.section-desc {
  font-size: 12.5px;
  color: var(--muted);
}

.search-input-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  border-radius: 12px;
}

.search-ico {
  font-size: 18px;
  color: var(--muted);
}

.friend-search-input {
  flex: 1;
  border: 0;
  background: transparent;
  color: var(--ink);
  font-size: 13px;
  outline: none;
}

.clear-search-btn {
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  padding: 2px;
  display: flex;
}

.friends-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 10px;
}

.friend-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.015));
  cursor: pointer;
  transition: all 0.15s ease;
}

.friend-card:hover {
  border-color: rgba(219, 39, 119, 0.4);
  background: var(--surface);
}

.friend-card.active {
  border-color: var(--pink-dark, #db2777);
  background: var(--pink-light, rgba(236, 72, 153, 0.06));
}

.avt-col {
  position: relative;
}

.f-avt {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.f-avt-placeholder {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--surface);
  border: 1.5px solid var(--outline);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.selected-dot {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--pink-dark, #db2777);
  border: 2px solid var(--surface);
}

.f-info-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.f-name-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
}

.f-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.f-rep-badge {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 10.5px;
  font-weight: 700;
  color: #10b981;
}

.rep-ico {
  font-size: 13px;
}

.f-tags-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.f-skill-pill {
  font-size: 10.5px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 6px;
  background: var(--surface);
  border: 1px solid var(--outline);
  color: var(--muted);
}

.f-city-txt {
  font-size: 10.5px;
  color: var(--muted);
}

.invite-toggle-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.invite-toggle-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.invite-toggle-btn.invited {
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  border-color: var(--pink-dark, #db2777);
}

.btn-ico {
  font-size: 14px;
}

@media (max-width: 600px) {
  .friends-grid {
    grid-template-columns: 1fr;
  }
}
</style>
