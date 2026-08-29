<script setup lang="ts">
import { ref } from 'vue'
import type { OrgMember } from '../../composables/useOrganizationOwner'

defineProps<{
  members: OrgMember[]
}>()

const emit = defineEmits<{
  (e: 'invite', email: string, role: string): void
}>()

const emailInput = ref('')
const roleInput = ref('staff')

function handleInvite() {
  if (!emailInput.value.trim()) return
  emit('invite', emailInput.value.trim(), roleInput.value)
  emailInput.value = ''
}
</script>

<template>
  <div class="org-members-tab-card">
    <div class="card-header">
      <h3 class="tab-title">👥 Đội Ngũ Quản Trị Doanh Nghiệp</h3>
      <span class="sub">Phân quyền quản lý cụm sân, nhân viên thu ngân và vận hành</span>
    </div>

    <!-- Quick invite form -->
    <div class="invite-form-box">
      <input
        v-model="emailInput"
        type="email"
        placeholder="Nhập email nhân sự cần mời..."
        class="form-input"
      />
      <select v-model="roleInput" class="form-input role-select">
        <option value="venue_manager">Quản Lý Cơ Sở (Manager)</option>
        <option value="cashier">Thu Ngân (Cashier)</option>
        <option value="staff">Nhân Viên Sân (Staff)</option>
      </select>
      <button class="btn-invite" :disabled="!emailInput.trim()" @click="handleInvite">
        + Gửi Lời Mời
      </button>
    </div>

    <div class="members-table">
      <div class="table-hdr">
        <span>Thành Viên</span>
        <span>Email</span>
        <span>Vai Trò</span>
        <span>Trạng Thái</span>
      </div>

      <div class="table-body">
        <div v-for="m in members" :key="m.id" class="member-row">
          <strong>{{ m.user?.name || 'Nhân sự' }}</strong>
          <span class="email-val">{{ m.user?.email }}</span>
          <span class="role-badge">{{ m.role }}</span>
          <span class="status-badge" :class="`status-${m.status}`">● {{ m.status }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.org-members-tab-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.tab-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.invite-form-box {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.invite-form-box input {
  flex: 1;
  min-width: 220px;
}

.role-select {
  width: 200px;
}

.btn-invite {
  padding: 8px 18px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.members-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.5fr 2fr 1.5fr 1fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.member-row {
  display: grid;
  grid-template-columns: 1.5fr 2fr 1.5fr 1fr;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.member-row:last-child {
  border-bottom: 0;
}

.email-val {
  color: var(--muted);
  font-size: 12px;
}

.role-badge {
  font-size: 11px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.status-badge {
  font-size: 11px;
  font-weight: 700;
  color: #10b981;
}
</style>
