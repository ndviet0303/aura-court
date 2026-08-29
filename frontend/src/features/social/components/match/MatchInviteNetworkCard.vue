<script setup lang="ts">
import type { FriendInvite } from '../../composables/useCreateMatch'

defineProps<{
  friends: FriendInvite[]
}>()

const emit = defineEmits<{
  (e: 'toggle', id: number): void
}>()
</script>

<template>
  <div class="invite-network-card">
    <div class="card-header">
      <h3 class="title">👥 Mạng Lưới Bạn Bè (Invite Network)</h3>
      <p class="sub">Mời trực tiếp bạn chơi quen thuộc từ danh sách bạn bè Aura.</p>
    </div>

    <div class="friends-list">
      <div
        v-for="f in friends"
        :key="f.id"
        class="friend-item"
        :class="{ selected: f.isSelected }"
        @click="emit('toggle', f.id)"
      >
        <img :src="f.avatar" class="f-avt" />
        <div class="f-info">
          <strong class="f-name">{{ f.name }}</strong>
          <span class="f-skill">{{ f.skill }}</span>
        </div>
        <span class="check-indicator">{{ f.isSelected ? '✓ Đã chọn' : '+ Mời' }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.invite-network-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.friends-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 10px;
}

.friend-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  cursor: pointer;
  transition: all 0.15s;
}

.friend-item.selected {
  border-color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.05);
}

.f-avt {
  width: 36px;
  height: 36px;
  border-radius: 50%;
}

.f-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.f-name {
  font-size: 13px;
}

.f-skill {
  font-size: 11px;
  color: var(--muted);
}

.check-indicator {
  font-size: 11px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}
</style>
