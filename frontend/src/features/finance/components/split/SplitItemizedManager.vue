<script setup lang="ts">
import type { SplitItemDetail, SplitMember } from '../../types/split'

defineProps<{
  items: SplitItemDetail[]
  members: SplitMember[]
}>()

const emit = defineEmits<{
  (e: 'toggleAssignment', itemId: string, memberId: number): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function isAssigned(item: SplitItemDetail, memberId: number): boolean {
  if (!item.assignedMemberIds || item.assignedMemberIds.length === 0) {
    return true // Default shared by all
  }
  return item.assignedMemberIds.includes(memberId)
}
</script>

<template>
  <div class="itemized-manager">
    <div class="itemized-header">
      <div class="header-text">
        <strong class="title">Phân Bổ Chi Phí Từng Món Cho Người Dùng</strong>
        <p class="sub">Nhấp vào tên thành viên để chọn ai tham gia chi trả món đó (Mặc định: Tất cả).</p>
      </div>
    </div>

    <div class="items-assignment-grid">
      <div v-for="item in items" :key="item.id" class="assignment-card">
        <div class="assign-top">
          <div class="item-title-col">
            <span class="item-name">{{ item.name }}</span>
            <span class="item-cost font-mono">{{ formatMoney(item.total) }} (x{{ item.qty }})</span>
          </div>
          <span class="shared-type-pill">
            {{ (!item.assignedMemberIds || item.assignedMemberIds.length === 0 || item.assignedMemberIds.length === members.length) ? 'Chia đều cả nhóm' : `Chia ${item.assignedMemberIds.length} người` }}
          </span>
        </div>

        <div class="members-chips-wrap">
          <button
            v-for="m in members"
            :key="m.id"
            type="button"
            class="member-chip"
            :class="{ 'member-chip--active': isAssigned(item, m.id) }"
            @click="emit('toggleAssignment', item.id, m.id)"
          >
            <span class="chip-avatar">{{ m.name.charAt(0) }}</span>
            <span class="chip-name">{{ m.name }}</span>
            <span class="material-symbols-outlined chip-check">
              {{ isAssigned(item, m.id) ? 'check' : 'add' }}
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.itemized-manager {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border-radius: 14px;
  background: var(--surface-container, rgba(0, 0, 0, 0.2));
  border: 1px solid var(--border, var(--outline, #382f35));
}

.itemized-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  font-size: 13.5px;
  color: var(--on-surface, #FAF7F3);
}

.sub {
  margin: 2px 0 0;
  font-size: 11.5px;
  color: var(--muted, #9e939a);
}

.items-assignment-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.assignment-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.06));
}

.assign-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.item-title-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.item-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--on-surface, #FAF7F3);
}

.item-cost {
  font-size: 11.5px;
  color: var(--primary, #E58E9F);
}

.shared-type-pill {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 5px;
  background: rgba(255, 255, 255, 0.05);
  color: var(--muted, #9e939a);
}

.members-chips-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.member-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: var(--surface, #1e191c);
  color: var(--muted, #9e939a);
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}

.member-chip:hover {
  border-color: var(--primary, #E58E9F);
}

.member-chip--active {
  border-color: var(--primary, #E58E9F);
  background: rgba(229, 142, 159, 0.15);
  color: var(--on-surface, #FAF7F3);
}

.chip-avatar {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--primary, #E58E9F);
  color: #1c0a10;
  font-weight: 800;
  font-size: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.chip-check {
  font-size: 14px;
  color: var(--primary, #E58E9F);
}
</style>
