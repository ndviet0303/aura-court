<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

type Organization = {
  id: number
  name: string
  slug: string
  tax_code?: string
  status: string
  venues?: Array<{ id: number; name: string; is_active: boolean; courts?: Array<{ id: number; name: string }> }>
  members?: Array<{ id: number; user_id: number; role: string; status: string; user?: { id: number; name: string; email: string } }>
}

const auth = useAuthStore()
const activeTab = ref<'profile' | 'venues' | 'members'>('profile')
const organizations = ref<Organization[]>([])
const currentOrg = ref<Organization | null>(null)
const error = ref('')
const notice = ref('')
const loading = ref(false)

// Member Form
const newMemberEmail = ref('')
const newMemberRole = ref('staff')
const addingMember = ref(false)

async function loadOrganizations(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get<{ organizations: Organization[] }>('/organizations')
    organizations.value = res.organizations || []
    if (organizations.value.length > 0 && organizations.value[0]) {
      const first = organizations.value[0]
      currentOrg.value = first
      await loadOrgDetails(first.id)
    }
  } catch (err) {
    error.value = errorMessage(err, 'Không tải được thông tin tổ chức.')
  } finally {
    loading.value = false
  }
}

async function loadOrgDetails(orgId: number): Promise<void> {
  try {
    const res = await api.get<{ organization: Organization }>(`/organizations/${orgId}`)
    currentOrg.value = res.organization
  } catch (err) {
    error.value = errorMessage(err, 'Không tải được chi tiết tổ chức.')
  }
}

async function updateProfile(): Promise<void> {
  if (!currentOrg.value) return
  loading.value = true
  notice.value = ''
  error.value = ''
  try {
    const res = await api.patch<{ organization: Organization }>(`/organizations/${currentOrg.value.id}`, {
      name: currentOrg.value.name,
      tax_code: currentOrg.value.tax_code,
    })
    currentOrg.value = res.organization
    notice.value = 'Đã cập nhật hồ sơ tổ chức thành công.'
  } catch (err) {
    error.value = errorMessage(err, 'Cập nhật tổ chức thất bại.')
  } finally {
    loading.value = false
  }
}

async function addMember(): Promise<void> {
  if (!currentOrg.value || !newMemberEmail.value) return
  addingMember.value = true
  error.value = ''
  notice.value = ''
  try {
    await api.post(`/organizations/${currentOrg.value.id}/members`, {
      email: newMemberEmail.value,
      role: newMemberRole.value,
    })
    notice.value = `Đã phân quyền thành công cho ${newMemberEmail.value}.`
    newMemberEmail.value = ''
    await loadOrgDetails(currentOrg.value.id)
  } catch (err) {
    error.value = errorMessage(err, 'Không thể thêm thành viên.')
  } finally {
    addingMember.value = false
  }
}

async function removeMember(memberId: number): Promise<void> {
  if (!currentOrg.value) return
  if (!confirm('Bạn có chắc muốn xoá quyền của thành viên này?')) return
  try {
    await api.delete(`/organizations/${currentOrg.value.id}/members/${memberId}`)
    notice.value = 'Đã xoá thành viên.'
    await loadOrgDetails(currentOrg.value.id)
  } catch (err) {
    error.value = errorMessage(err, 'Không thể xoá thành viên.')
  }
}

onMounted(() => {
  loadOrganizations()
})
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 p-6 md:p-10 max-w-7xl mx-auto w-full">
    <div class="max-w-6xl mx-auto">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-6 mb-8">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center gap-3">
            <span class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20">
              🏢
            </span>
            Quản trị Tổ chức & Cụm sân
          </h1>
          <p class="text-slate-400 mt-1">Phân quyền vai trò, quản lý cơ sở và vận hành đa cụm sân độc lập</p>
        </div>

        <div v-if="organizations.length > 1" class="flex items-center gap-3">
          <label class="text-sm text-slate-400">Tổ chức:</label>
          <select
            v-model="currentOrg"
            @change="currentOrg && loadOrgDetails(currentOrg.id)"
            class="bg-slate-900 border border-slate-700 text-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500"
          >
            <option v-for="org in organizations" :key="org.id" :value="org">
              {{ org.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="notice" class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
        {{ notice }}
      </div>
      <div v-if="error" class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
        {{ error }}
      </div>

      <!-- Navigation Tabs -->
      <div class="flex gap-2 border-b border-slate-800 mb-8">
        <button
          @click="activeTab = 'profile'"
          :class="['px-5 py-3 text-sm font-medium border-b-2 transition-all', activeTab === 'profile' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-white']"
        >
          Hồ sơ Tổ chức
        </button>
        <button
          @click="activeTab = 'venues'"
          :class="['px-5 py-3 text-sm font-medium border-b-2 transition-all', activeTab === 'venues' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-white']"
        >
          Cơ sở & Sân đấu ({{ currentOrg?.venues?.length || 0 }})
        </button>
        <button
          @click="activeTab = 'members'"
          :class="['px-5 py-3 text-sm font-medium border-b-2 transition-all', activeTab === 'members' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-white']"
        >
          Phân quyền Nhân viên ({{ currentOrg?.members?.length || 0 }})
        </button>
      </div>

      <!-- Tab: Profile -->
      <section v-if="activeTab === 'profile' && currentOrg" class="bg-slate-900/60 rounded-2xl border border-slate-800 p-6 md:p-8">
        <h2 class="text-xl font-bold text-white mb-6">Thông tin Pháp lý & Doanh nghiệp</h2>
        <form @submit.prevent="updateProfile" class="space-y-6 max-w-xl">
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Tên Tổ chức / Doanh nghiệp</label>
            <input
              v-model="currentOrg.name"
              type="text"
              required
              class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Mã số thuế (Tax Code / CCCD)</label>
            <input
              v-model="currentOrg.tax_code"
              type="text"
              placeholder="0312345678"
              class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Slug định danh</label>
            <input
              :value="currentOrg.slug"
              disabled
              class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-500 cursor-not-allowed"
            />
          </div>
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition shadow-lg shadow-emerald-900/30 disabled:opacity-50"
          >
            {{ loading ? 'Đang lưu...' : 'Lưu thay đổi' }}
          </button>
        </form>
      </section>

      <!-- Tab: Venues -->
      <section v-if="activeTab === 'venues' && currentOrg" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div
            v-for="venue in currentOrg.venues"
            :key="venue.id"
            class="bg-slate-900/60 rounded-2xl border border-slate-800 p-6 flex flex-col justify-between"
          >
            <div>
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-bold text-white">{{ venue.name }}</h3>
                <span :class="['px-2.5 py-1 text-xs rounded-full font-medium', venue.is_active ? 'bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20' : 'bg-slate-800 text-slate-400']">
                  {{ venue.is_active ? 'Đang hoạt động' : 'Tạm dừng' }}
                </span>
              </div>
              <p class="text-sm text-slate-400 mb-4">Tổng số sân thi đấu: <strong class="text-slate-200">{{ venue.courts?.length || 0 }} sân</strong></p>
              <div class="flex flex-wrap gap-2">
                <span v-for="court in venue.courts" :key="court.id" class="px-3 py-1 bg-slate-800 text-xs rounded-lg text-slate-300">
                  {{ court.name }}
                </span>
              </div>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-800/80 flex justify-end">
              <router-link to="/admin/manage" class="text-sm font-medium text-emerald-400 hover:text-emerald-300">
                Quản lý bảng giá & khung giờ &rarr;
              </router-link>
            </div>
          </div>
        </div>
      </section>

      <!-- Tab: Members & Role-Based Access -->
      <section v-if="activeTab === 'members' && currentOrg" class="bg-slate-900/60 rounded-2xl border border-slate-800 p-6 md:p-8">
        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6">
          <div>
            <h2 class="text-xl font-bold text-white">Danh sách Nhân sự & Vai trò</h2>
            <p class="text-sm text-slate-400">Kiểm soát quyền truy cập chi tiết theo vai trò</p>
          </div>
        </div>

        <!-- Add member form -->
        <form @submit.prevent="addMember" class="bg-slate-950/80 p-4 rounded-xl border border-slate-800 flex flex-col md:flex-row gap-4 mb-8">
          <div class="flex-1">
            <input
              v-model="newMemberEmail"
              type="email"
              placeholder="Email nhân viên (ví dụ: staff@auracourt.vn)"
              required
              class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>
          <div class="w-full md:w-56">
            <select
              v-model="newMemberRole"
              class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
              <option value="venue_manager">Quản lý Cụm sân (Manager)</option>
              <option value="cashier">Thu ngân (Cashier)</option>
              <option value="staff">Nhân viên trực sân (Staff)</option>
              <option value="organization_owner">Chủ sở hữu (Owner)</option>
            </select>
          </div>
          <button
            type="submit"
            :disabled="addingMember"
            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50"
          >
            {{ addingMember ? 'Đang thêm...' : 'Phân quyền' }}
          </button>
        </form>

        <!-- Members Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-300">
            <thead class="text-xs uppercase bg-slate-800/60 text-slate-400 border-b border-slate-700">
              <tr>
                <th class="px-4 py-3">Nhân viên</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Vai trò</th>
                <th class="px-4 py-3">Trạng thái</th>
                <th class="px-4 py-3 text-right">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-for="member in currentOrg.members" :key="member.id" class="hover:bg-slate-800/30">
                <td class="px-4 py-3 font-medium text-white">{{ member.user?.name || 'Thành viên' }}</td>
                <td class="px-4 py-3">{{ member.user?.email || '-' }}</td>
                <td class="px-4 py-3">
                  <span class="px-2.5 py-1 text-xs rounded-full font-medium bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20">
                    {{ member.role }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="text-xs text-emerald-400 font-semibold">● Hoạt động</span>
                </td>
                <td class="px-4 py-3 text-right">
                  <button
                    v-if="member.user_id !== auth.user?.id"
                    @click="removeMember(member.id)"
                    class="text-xs text-rose-400 hover:text-rose-300 font-medium"
                  >
                    Xoá quyền
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>
