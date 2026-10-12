<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { socialApi } from '../api/socialApi'
import { errorMessage } from '@/core/utils/errorMessage'

const activeSubTab = ref<'friends' | 'requests'>('friends')
const loading = ref(false)
const friends = ref<any[]>([])
const incomingRequests = ref<any[]>([])
const outgoingRequests = ref<any[]>([])
const searchQuery = ref('')
const targetUserId = ref<number | ''>('')
const notice = ref('')
const error = ref('')

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [friendsRes, reqsRes] = await Promise.all([
      socialApi.getFriends(searchQuery.value),
      socialApi.getFriendRequests(),
    ])
    friends.value = (friendsRes as any)?.friends || []
    incomingRequests.value = (reqsRes as any)?.incoming || []
    outgoingRequests.value = (reqsRes as any)?.outgoing || []
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể tải danh sách kết nối bạn bè.')
  } finally {
    loading.value = false
  }
}

async function handleSearch() {
  try {
    const res = await socialApi.getFriends(searchQuery.value)
    friends.value = (res as any)?.friends || []
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Lỗi tìm kiếm bạn bè.')
  }
}

async function handleSendRequest() {
  if (!targetUserId.value) return
  error.value = ''
  notice.value = ''
  try {
    await socialApi.sendFriendRequest(Number(targetUserId.value))
    notice.value = 'Đã gửi lời mời kết bạn thành công!'
    targetUserId.value = ''
    await loadData()
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể gửi lời mời kết bạn.')
  }
}

async function handleAccept(friendshipId: number) {
  try {
    await socialApi.acceptFriendRequest(friendshipId)
    notice.value = 'Đã chấp nhận lời mời kết bạn!'
    await loadData()
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể chấp nhận lời mời.')
  }
}

async function handleDecline(friendshipId: number) {
  try {
    await socialApi.declineFriendRequest(friendshipId)
    notice.value = 'Đã từ chối lời mời kết bạn.'
    await loadData()
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể từ chối lời mời.')
  }
}

async function handleRemove(friendshipId: number) {
  try {
    await socialApi.removeFriend(friendshipId)
    notice.value = 'Đã xóa kết bạn.'
    await loadData()
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể xóa kết bạn.')
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="connections-panel-container">
    <!-- Notice & Error Alerts -->
    <div v-if="notice" class="connections-alert success">{{ notice }}</div>
    <div v-if="error" class="connections-alert error">{{ error }}</div>

    <!-- Sub-tabs Row -->
    <div class="connections-subtabs">
      <button
        type="button"
        class="subtab-btn"
        :class="{ active: activeSubTab === 'friends' }"
        @click="activeSubTab = 'friends'"
      >
        <span class="material-symbols-outlined sub-ico">group</span>
        <span>Bạn bè ({{ friends.length }})</span>
      </button>

      <button
        type="button"
        class="subtab-btn"
        :class="{ active: activeSubTab === 'requests' }"
        @click="activeSubTab = 'requests'"
      >
        <span class="material-symbols-outlined sub-ico">person_add</span>
        <span>Lời mời kết bạn ({{ incomingRequests.length }})</span>
      </button>
    </div>

    <!-- Friends View -->
    <div v-if="activeSubTab === 'friends'" class="friends-view">
      <!-- Search & Add Row -->
      <div class="action-toolbar">
        <div class="search-box">
          <span class="material-symbols-outlined">search</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Tìm theo tên, email, số điện thoại..."
            @input="handleSearch"
          />
        </div>

        <div class="add-friend-box">
          <input
            v-model="targetUserId"
            type="number"
            placeholder="ID người chơi..."
            class="user-id-input"
          />
          <button
            type="button"
            class="btn-send-req"
            :disabled="!targetUserId"
            @click="handleSendRequest"
          >
            Kết bạn
          </button>
        </div>
      </div>

      <!-- Friends List -->
      <div v-if="loading" class="panel-loading">Đang tải danh sách...</div>
      <div v-else-if="friends.length === 0" class="panel-empty">
        Chưa có bạn bè nào trong danh bạ kết nối.
      </div>
      <div v-else class="friends-list-grid">
        <div v-for="f in friends" :key="f.id" class="friend-card-row">
          <div class="f-avt font-mono">
            {{ (f.name || 'U').charAt(0).toUpperCase() }}
          </div>
          <div class="f-details">
            <strong class="f-name">{{ f.name }}</strong>
            <span class="f-meta">{{ f.email || f.phone }}</span>
          </div>
          <button
            type="button"
            class="btn-unfriend"
            title="Hủy kết bạn"
            @click="handleRemove(f.friendship_id)"
          >
            <span class="material-symbols-outlined">person_remove</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Requests View -->
    <div v-else class="requests-view">
      <!-- Incoming Requests -->
      <div class="req-section">
        <h4>Lời mời kết bạn nhận được ({{ incomingRequests.length }})</h4>
        <div v-if="incomingRequests.length === 0" class="req-empty">
          Không có lời mời kết bạn nào đang chờ.
        </div>
        <div v-else class="req-list">
          <div v-for="req in incomingRequests" :key="req.id" class="req-item">
            <div class="req-user">
              <strong>{{ req.requester?.name || 'Người chơi' }}</strong>
              <span>{{ req.requester?.email }}</span>
            </div>
            <div class="req-actions">
              <button type="button" class="btn-accept" @click="handleAccept(req.id)">Đồng ý</button>
              <button type="button" class="btn-decline" @click="handleDecline(req.id)">
                Từ chối
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Outgoing Requests -->
      <div class="req-section">
        <h4>Lời mời đã gửi đi ({{ outgoingRequests.length }})</h4>
        <div v-if="outgoingRequests.length === 0" class="req-empty">
          Không có lời mời nào đã gửi đi.
        </div>
        <div v-else class="req-list">
          <div v-for="req in outgoingRequests" :key="req.id" class="req-item">
            <div class="req-user">
              <strong>{{ req.addressee?.name || 'Người chơi' }}</strong>
              <span>{{ req.addressee?.email }}</span>
            </div>
            <div class="req-actions">
              <button type="button" class="btn-cancel-req" @click="handleRemove(req.id)">
                Hủy lời mời
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.connections-panel-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 800px;
  margin: 0 auto;
}
.connections-alert {
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13.5px;
}
.connections-alert.success {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}
.connections-alert.error {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}
.connections-subtabs {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  padding-bottom: 10px;
}
.subtab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #94a3b8;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.subtab-btn.active {
  background: #3b82f6;
  color: #fff;
}
.action-toolbar {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 8px;
  padding: 6px 12px;
  flex: 1;
  min-width: 240px;
}
.search-box input {
  background: transparent;
  border: none;
  color: #fff;
  font-size: 13px;
  width: 100%;
}
.add-friend-box {
  display: flex;
  gap: 8px;
}
.user-id-input {
  width: 120px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 8px;
  padding: 6px 10px;
  color: #fff;
  font-size: 13px;
}
.btn-send-req {
  background: #10b981;
  color: #fff;
  border: none;
  padding: 6px 14px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}
.btn-send-req:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.friends-list-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}
.friend-card-row {
  background: var(--surface, #1e293b);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 12px;
  padding: 12px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.f-avt {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: #6366f1;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}
.f-details {
  display: flex;
  flex-direction: column;
  flex: 1;
}
.f-name {
  color: #fff;
  font-size: 14px;
}
.f-meta {
  color: #94a3b8;
  font-size: 12px;
}
.btn-unfriend {
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
}
.btn-unfriend:hover {
  color: #ef4444;
}
.req-section {
  background: var(--surface, #1e293b);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 14px;
}
.req-section h4 {
  margin: 0 0 12px 0;
  color: #fff;
  font-size: 14px;
}
.req-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.req-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
}
.req-user {
  display: flex;
  flex-direction: column;
}
.req-user strong {
  color: #fff;
  font-size: 13.5px;
}
.req-user span {
  color: #94a3b8;
  font-size: 11.5px;
}
.req-actions {
  display: flex;
  gap: 8px;
}
.btn-accept {
  background: #10b981;
  color: #fff;
  border: none;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
}
.btn-decline,
.btn-cancel-req {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
}
.panel-loading,
.panel-empty,
.req-empty {
  color: #94a3b8;
  font-size: 13.5px;
  text-align: center;
  padding: 20px;
}
</style>
