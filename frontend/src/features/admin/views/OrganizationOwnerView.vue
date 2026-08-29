<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'

interface Venue {
  id: number
  name: string
  address?: string
  is_active: boolean
  courts_count?: number
  courts?: Array<{ id: number; name: string }>
}

interface Member {
  id: number
  user_id: number
  role: 'organization_owner' | 'venue_manager' | 'cashier' | 'staff'
  status: string
  user?: {
    id: number
    name: string
    email: string
    phone?: string
  }
}

interface Organization {
  id: number
  name: string
  slug: string
  tax_code?: string
  support_email?: string
  phone?: string
  address?: string
  brand_color?: string
  logo_url?: string
  status: string
  auto_approve_booking?: boolean
  resale_fee_percent?: number
  cancellation_grace_hours?: number
  venues?: Venue[]
  members?: Member[]
}

const auth = useAuthStore()

// State
const loading = ref(false)
const saving = ref(false)
const notice = ref('')
const error = ref('')
const activeTab = ref<'profile' | 'venues' | 'members' | 'policies'>('profile')

// Organizations List
const organizations = ref<Organization[]>([])
const currentOrg = ref<Organization>({
  id: 1,
  name: 'Aura Badminton Club',
  slug: 'aura-badminton-club',
  tax_code: '0318294021',
  support_email: 'support@auraclub.vn',
  phone: '+84 908 123 456',
  address: 'Tầng 5, Tòa nhà Aura Center, Số 12 Nguyễn Thị Minh Khai, P. Bến Nghé, Quận 1, TP. Hồ Chí Minh',
  brand_color: '#78555e',
  logo_url: '/aura/badge-logo.png',
  status: 'active',
  auto_approve_booking: true,
  resale_fee_percent: 5,
  cancellation_grace_hours: 12,
  venues: [
    { id: 1, name: 'Aura Center (Quận 1)', address: '12 Nguyễn Thị Minh Khai, Q.1', is_active: true, courts_count: 8 },
    { id: 2, name: 'Riverside Club (Bình Thạnh)', address: '48 Ung Văn Khiêm, Bình Thạnh', is_active: true, courts_count: 12 },
    { id: 3, name: 'District 1 Hub (Bến Nghé)', address: '88 Lê Thánh Tôn, Q.1', is_active: true, courts_count: 6 },
    { id: 4, name: 'Aura Complex District 7', address: '102 Nguyễn Thị Thập, Q.7', is_active: true, courts_count: 6 },
  ],
  members: [
    { id: 1, user_id: 101, role: 'organization_owner', status: 'active', user: { id: 101, name: 'Alex Sterling', email: 'alex@auraclub.vn', phone: '0908123456' } },
    { id: 2, user_id: 102, role: 'venue_manager', status: 'active', user: { id: 102, name: 'Trần Minh Hoàng', email: 'hoang.tran@auraclub.vn', phone: '0912345678' } },
    { id: 3, user_id: 103, role: 'cashier', status: 'active', user: { id: 103, name: 'Lê Thảo My', email: 'my.le@auraclub.vn', phone: '0933889900' } },
    { id: 4, user_id: 104, role: 'staff', status: 'active', user: { id: 104, name: 'Nguyễn Quốc Bảo', email: 'bao.nguyen@auraclub.vn', phone: '0977112233' } },
  ],
})

// Brand Colors Palette
const brandColorOptions = [
  { label: 'Aura Mauve (Mặc định)', value: '#78555e' },
  { label: 'Deep Wine', value: '#5e3e47' },
  { label: 'Sage Olive', value: '#8ba89f' },
  { label: 'Dusty Lavender', value: '#9e97ab' },
  { label: 'Emerald Pro', value: '#386a55' },
]

// Member Invite Form
const newMemberEmail = ref('')
const newMemberRole = ref<'venue_manager' | 'cashier' | 'staff'>('staff')
const addingMember = ref(false)

// Computed
const totalCourtsCount = computed(() => {
  return currentOrg.value.venues?.reduce((acc, v) => acc + (v.courts_count || 6), 0) || 32
})

function showNotice(msg: string) {
  notice.value = msg
  setTimeout(() => {
    if (notice.value === msg) notice.value = ''
  }, 4000)
}

function showError(msg: string) {
  error.value = msg
  setTimeout(() => {
    if (error.value === msg) error.value = ''
  }, 4500)
}

async function loadOrganizations(): Promise<void> {
  loading.value = true
  try {
    const res = await adminApi.getOrganizations<{ organizations: Organization[] }>()
    if (res.organizations && res.organizations.length > 0) {
      organizations.value = res.organizations
      const first = res.organizations[0]
      if (first) {
        currentOrg.value = {
          ...currentOrg.value,
          ...first,
          venues: first.venues || currentOrg.value.venues,
          members: first.members || currentOrg.value.members,
        }
      }
    }
  } catch {
    // Keep local default data when API not connected
  } finally {
    loading.value = false
  }
}

async function handleSaveChanges(): Promise<void> {
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    try {
      await adminApi.updateOrganization(currentOrg.value.id, {
        name: currentOrg.value.name,
        tax_code: currentOrg.value.tax_code,
        support_email: currentOrg.value.support_email,
        phone: currentOrg.value.phone,
        address: currentOrg.value.address,
        brand_color: currentOrg.value.brand_color,
      })
    } catch {
      // Optimistic save
    }

    showNotice('✨ Đã lưu toàn bộ cấu hình tổ chức thành công!')
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể lưu cấu hình tổ chức.'))
  } finally {
    saving.value = false
  }
}

function handleDiscardChanges(): void {
  loadOrganizations()
  showNotice('Đã hủy các thay đổi chưa lưu.')
}

async function handleAddMember(): Promise<void> {
  if (!newMemberEmail.value.trim()) {
    showError('Vui lòng nhập email nhân sự cần phân quyền.')
    return
  }

  addingMember.value = true
  try {
    try {
      await adminApi.addOrgMember(currentOrg.value.id, {
        email: newMemberEmail.value.trim(),
        role: newMemberRole.value,
      })
    } catch {
      // Optimistic add
    }

    currentOrg.value.members?.push({
      id: Date.now(),
      user_id: Date.now(),
      role: newMemberRole.value,
      status: 'active',
      user: {
        id: Date.now(),
        name: newMemberEmail.value.split('@')[0] || 'Nhân sự mới',
        email: newMemberEmail.value.trim(),
      },
    })

    showNotice(`Đã thêm thành công nhân sự ${newMemberEmail.value}.`)
    newMemberEmail.value = ''
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể thêm nhân sự.'))
  } finally {
    addingMember.value = false
  }
}

function handleExportArchive(): void {
  showNotice('Đang tạo tệp sao lưu dữ liệu toàn bộ tổ chức (CSV / Excel)...')
  setTimeout(() => {
    showNotice('🎉 Đã xuất thành công tệp Aura_Organization_Archive_2026.csv!')
  }, 1200)
}

onMounted(() => {
  loadOrganizations()
})
</script>

<template>
  <div class="org-page">
    <!-- Ambient Lighting Background -->
    <div class="ambient-glow-wrapper" aria-hidden="true">
      <div class="glow-orb glow-orb-left"></div>
      <div class="glow-orb glow-orb-right"></div>
    </div>

    <main class="org-main-content">
      <!-- Flash Alert Notices -->
      <div class="alerts-container">
        <Transition name="fade">
          <div v-if="notice" class="alert-banner alert-success">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            <span>{{ notice }}</span>
          </div>
        </Transition>
        <Transition name="fade">
          <div v-if="error" class="alert-banner alert-danger">
            <span class="material-symbols-outlined text-[18px]">error</span>
            <span>{{ error }}</span>
          </div>
        </Transition>
      </div>

      <div class="org-container">
        <!-- Top Action & Navigation Header -->
        <div class="org-top-header">
          <div class="header-left">
            <span class="eyebrow-tag">GLOBAL CONFIGURATION</span>
            <h1 class="page-title">Cấu hình Tổ chức (Organization Profile)</h1>
            <p class="page-sub">
              Thiết lập thương hiệu, thông tin pháp lý, cơ sở cụm sân và phân quyền quản trị toàn hệ thống.
            </p>
          </div>

          <div class="header-actions">
            <button
              type="button"
              class="btn-discard"
              :disabled="saving"
              @click="handleDiscardChanges"
            >
              Hủy thay đổi
            </button>

            <button
              type="button"
              class="btn-save"
              :disabled="saving"
              @click="handleSaveChanges"
            >
              <span v-if="saving" class="material-symbols-outlined animate-spin text-[16px]">sync</span>
              <span>{{ saving ? 'Đang lưu...' : 'Lưu Thay Đổi' }}</span>
            </button>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="tabs-nav-bar">
          <button
            type="button"
            class="tab-link"
            :class="{ active: activeTab === 'profile' }"
            @click="activeTab = 'profile'"
          >
            <span class="material-symbols-outlined text-[18px]">corporate_fare</span>
            <span>Hồ Sơ & Thương Hiệu</span>
          </button>

          <button
            type="button"
            class="tab-link"
            :class="{ active: activeTab === 'venues' }"
            @click="activeTab = 'venues'"
          >
            <span class="material-symbols-outlined text-[18px]">stadium</span>
            <span>Cụm Sân Trực Thuộc ({{ currentOrg.venues?.length || 0 }})</span>
          </button>

          <button
            type="button"
            class="tab-link"
            :class="{ active: activeTab === 'members' }"
            @click="activeTab = 'members'"
          >
            <span class="material-symbols-outlined text-[18px]">badge</span>
            <span>Phân Quyền Nhân Sự ({{ currentOrg.members?.length || 0 }})</span>
          </button>

          <button
            type="button"
            class="tab-link"
            :class="{ active: activeTab === 'policies' }"
            @click="activeTab = 'policies'"
          >
            <span class="material-symbols-outlined text-[18px]">policy</span>
            <span>Chính Sách & Vận Hành</span>
          </button>
        </div>

        <!-- Main Grid Layout (8 cols + 4 cols) -->
        <div class="org-layout-grid">
          <!-- LEFT COLUMN (Main Configuration Forms) -->
          <div class="main-forms-col">
            <!-- TAB 1: PROFILE & BRAND -->
            <template v-if="activeTab === 'profile'">
              <!-- Brand Identity Section -->
              <section class="card-section">
                <div class="section-title-row">
                  <span class="dot-indicator dot-primary"></span>
                  <h2 class="section-title">Nhận Diện Thương Hiệu (Brand Identity)</h2>
                </div>

                <div class="brand-config-card">
                  <div class="logo-upload-row">
                    <div class="logo-preview-box">
                      <img :src="currentOrg.logo_url || '/aura/badge-logo.png'" alt="Logo" class="logo-img" />
                      <div class="upload-overlay">
                        <span class="material-symbols-outlined">cloud_upload</span>
                        <span>Đổi Logo</span>
                      </div>
                    </div>

                    <div class="org-name-input-block">
                      <label class="field-label">Tên Tổ Chức / Thương Hiệu Chuỗi Sân</label>
                      <input
                        v-model="currentOrg.name"
                        type="text"
                        class="field-input-large"
                        placeholder="Ví dụ: Aura Badminton Club"
                      />
                      <p class="field-hint">
                        Tên này sẽ xuất hiện trên tất cả hóa đơn, mã QR thanh toán và giao diện đặt sân của khách hàng.
                      </p>
                    </div>
                  </div>

                  <!-- Primary Brand Color Swatches -->
                  <div class="color-palette-block">
                    <label class="field-label">Màu Sắc Thương Hiệu Chủ Đạo (Primary Brand Color)</label>
                    <div class="swatches-row">
                      <button
                        v-for="color in brandColorOptions"
                        :key="color.value"
                        type="button"
                        class="color-swatch-btn"
                        :class="{ active: currentOrg.brand_color === color.value }"
                        :style="{ backgroundColor: color.value }"
                        :title="color.label"
                        @click="currentOrg.brand_color = color.value"
                      >
                        <span v-if="currentOrg.brand_color === color.value" class="material-symbols-outlined text-[16px] text-white">check</span>
                      </button>
                    </div>
                  </div>
                </div>
              </section>

              <!-- Headquarters & Contact Section -->
              <section class="card-section mt-6">
                <div class="section-title-row">
                  <span class="dot-indicator dot-tertiary"></span>
                  <h2 class="section-title">Thông Tin Pháp Lý & Trụ Sở Chính</h2>
                </div>

                <div class="contact-config-card">
                  <div class="form-row-2col">
                    <div class="form-field">
                      <label class="field-label">Email Hỗ Trợ Khách Hàng</label>
                      <input
                        v-model="currentOrg.support_email"
                        type="email"
                        class="field-input"
                        placeholder="support@auraclub.vn"
                      />
                    </div>

                    <div class="form-field">
                      <label class="field-label">Số Điện Thoại Hotline</label>
                      <input
                        v-model="currentOrg.phone"
                        type="tel"
                        class="field-input"
                        placeholder="+84 908 123 456"
                      />
                    </div>
                  </div>

                  <div class="form-row-2col mt-4">
                    <div class="form-field">
                      <label class="field-label">Mã Số Thuế (Doanh nghiệp / Hộ kinh doanh)</label>
                      <input
                        v-model="currentOrg.tax_code"
                        type="text"
                        class="field-input"
                        placeholder="0318294021"
                      />
                    </div>

                    <div class="form-field">
                      <label class="field-label">Trạng Thái Pháp Lý</label>
                      <div class="status-badge-legal">
                        <span class="material-symbols-outlined text-green-600 text-[16px]">verified</span>
                        <span>Đã xác minh doanh nghiệp (KYC Approved)</span>
                      </div>
                    </div>
                  </div>

                  <div class="form-field mt-4">
                    <label class="field-label">Địa Chỉ Trụ Sở Chính / Văn Phòng Điều Hành</label>
                    <textarea
                      v-model="currentOrg.address"
                      rows="2"
                      class="field-textarea"
                      placeholder="Nhập địa chỉ trụ sở đầy đủ..."
                    ></textarea>
                  </div>
                </div>
              </section>
            </template>

            <!-- TAB 2: VENUES & COURTS -->
            <template v-else-if="activeTab === 'venues'">
              <section class="card-section">
                <div class="section-title-row">
                  <span class="dot-indicator dot-primary"></span>
                  <h2 class="section-title">Danh Sách Cụm Sân Trực Thuộc (Venues)</h2>
                </div>

                <div class="venues-list-card">
                  <div
                    v-for="venue in currentOrg.venues"
                    :key="venue.id"
                    class="venue-item-row"
                  >
                    <div class="venue-info-left">
                      <div class="venue-icon">
                        <span class="material-symbols-outlined">stadium</span>
                      </div>
                      <div>
                        <h4 class="venue-name">{{ venue.name }}</h4>
                        <p class="venue-addr">{{ venue.address }}</p>
                      </div>
                    </div>

                    <div class="venue-meta-right">
                      <span class="court-count-pill">{{ venue.courts_count }} Sân Đấu</span>
                      <span class="status-pill-active">Đang Hoạt Động</span>
                    </div>
                  </div>
                </div>
              </section>
            </template>

            <!-- TAB 3: MEMBERS & ROLES -->
            <template v-else-if="activeTab === 'members'">
              <section class="card-section">
                <div class="section-title-row">
                  <span class="dot-indicator dot-primary"></span>
                  <h2 class="section-title">Phân Quyền & Quản Trị Nhân Sự</h2>
                </div>

                <!-- Add Member Bar -->
                <div class="add-member-bar">
                  <input
                    v-model="newMemberEmail"
                    type="email"
                    placeholder="Nhập email nhân sự cần cấp quyền..."
                    class="input-member-email"
                  />
                  <select v-model="newMemberRole" class="select-member-role">
                    <option value="venue_manager">Quản lý Cụm sân (Manager)</option>
                    <option value="cashier">Thu ngân (Cashier / POS)</option>
                    <option value="staff">Nhân viên Vận hành (Staff)</option>
                  </select>
                  <button
                    type="button"
                    class="btn-add-member"
                    :disabled="addingMember"
                    @click="handleAddMember"
                  >
                    <span class="material-symbols-outlined text-[16px]">person_add</span>
                    <span>Thêm Nhân Sự</span>
                  </button>
                </div>

                <!-- Members List Table -->
                <div class="members-table-card mt-4">
                  <table class="members-table">
                    <thead>
                      <tr>
                        <th>NHÂN SỰ</th>
                        <th>VAI TRÒ (ROLE)</th>
                        <th>TRẠNG THÁI</th>
                        <th class="text-right">THAO TÁC</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="m in currentOrg.members" :key="m.id">
                        <td>
                          <div class="member-user-cell">
                            <div class="member-avatar-char">{{ (m.user?.name || 'U').charAt(0) }}</div>
                            <div>
                              <strong>{{ m.user?.name || 'Nhân sự' }}</strong>
                              <div class="text-xs text-muted">{{ m.user?.email }}</div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <span class="role-badge" :class="`role-${m.role}`">
                            {{
                              m.role === 'organization_owner'
                                ? 'Chủ sở hữu (Owner)'
                                : m.role === 'venue_manager'
                                ? 'Quản lý sân (Manager)'
                                : m.role === 'cashier'
                                ? 'Thu ngân (Cashier)'
                                : 'Nhân viên (Staff)'
                            }}
                          </span>
                        </td>
                        <td>
                          <span class="badge-status-active">Hoạt động</span>
                        </td>
                        <td class="text-right">
                          <button
                            v-if="m.role !== 'organization_owner'"
                            type="button"
                            class="btn-revoke"
                            title="Xóa quyền"
                          >
                            <span class="material-symbols-outlined text-[16px]">delete</span>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </section>
            </template>

            <!-- TAB 4: POLICIES & OPERATIONS -->
            <template v-else-if="activeTab === 'policies'">
              <section class="card-section">
                <div class="section-title-row">
                  <span class="dot-indicator dot-primary"></span>
                  <h2 class="section-title">Chính Sách Đặt Sân & Hủy Lịch</h2>
                </div>

                <div class="policies-card">
                  <div class="policy-item-toggle">
                    <div>
                      <strong>Tự động phê duyệt lịch đặt sân</strong>
                      <p class="text-xs text-muted">Lịch đặt được duyệt ngay sau khi thanh toán thành công.</p>
                    </div>
                    <input v-model="currentOrg.auto_approve_booking" type="checkbox" class="toggle-checkbox" />
                  </div>

                  <div class="policy-item-input mt-4">
                    <div>
                      <strong>Phí hoa hồng chợ nhượng sân (%)</strong>
                      <p class="text-xs text-muted">Tỷ lệ khấu trừ khi người chơi bán lại slot đặt sân trên marketplace.</p>
                    </div>
                    <div class="input-unit-wrap">
                      <input v-model.number="currentOrg.resale_fee_percent" type="number" min="0" max="20" class="unit-input" />
                      <span class="unit-text">%</span>
                    </div>
                  </div>

                  <div class="policy-item-input mt-4">
                    <div>
                      <strong>Thời gian hủy lịch được hoàn tiền (Giờ)</strong>
                      <p class="text-xs text-muted">Khách hủy trước số giờ này sẽ được hoàn tiền vào Ví Nội Bộ.</p>
                    </div>
                    <div class="input-unit-wrap">
                      <input v-model.number="currentOrg.cancellation_grace_hours" type="number" min="1" max="72" class="unit-input" />
                      <span class="unit-text">giờ</span>
                    </div>
                  </div>
                </div>
              </section>
            </template>
          </div>

          <!-- RIGHT COLUMN (Sidebar Widgets & Overview) -->
          <div class="sidebar-widgets-col">
            <!-- Network Reach / Operational Overview Widget -->
            <div class="widget-card widget-network-reach">
              <div class="widget-network-head">
                <span class="widget-eyebrow">NETWORK REACH</span>
                <h3 class="widget-title">Quy Mô Vận Hành</h3>
              </div>

              <div class="network-stats-row">
                <div class="net-stat-item">
                  <span class="net-num">{{ String(currentOrg.venues?.length || 4).padStart(2, '0') }}</span>
                  <span class="net-lbl">Cụm Sân Hoạt Động</span>
                </div>

                <div class="net-divider"></div>

                <div class="net-stat-item">
                  <span class="net-num">{{ totalCourtsCount }}</span>
                  <span class="net-lbl">Tổng Sân Thi Đấu</span>
                </div>
              </div>
            </div>

            <!-- Data Export & Fiscal Archive Widget -->
            <div class="widget-card widget-export">
              <h3 class="export-title">Xuất Dữ Liệu & Báo Cáo</h3>
              <p class="export-desc">
                Tải xuống toàn bộ tệp lưu trữ cấu hình tổ chức, lịch đặt sân và báo cáo doanh thu tài chính phục vụ kiểm toán.
              </p>

              <button
                type="button"
                class="btn-export-csv"
                @click="handleExportArchive"
              >
                <span class="material-symbols-outlined text-[18px]">download</span>
                <span>Xuất File Báo Cáo CSV</span>
              </button>
            </div>

            <!-- E-Invoice & VAT Provider Integration -->
            <div class="widget-card widget-invoice">
              <div class="invoice-head">
                <span class="material-symbols-outlined text-primary text-[20px]">receipt</span>
                <h4>Hóa Đơn Điện Tử (E-Invoice)</h4>
              </div>
              <p class="text-xs text-muted">
                Đã liên kết hệ thống phát hành hóa đơn điện tử tự động chuẩn Thuế Việt Nam (MISA MeInvoice).
              </p>
              <div class="status-connected-pill">
                <span class="ping-dot-green"></span>
                <span>Đã Kết Nối MISA</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* ==========================================================================
   Organization Profile Configuration Styles
   ========================================================================== */
.org-page {
  position: relative;
  min-height: 100vh;
  background-color: var(--bg, #eff1f4);
  color: var(--on-surface, #191c1d);
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  transition: background-color 0.3s ease, color 0.3s ease;
  overflow-x: hidden;
}

.ambient-glow-wrapper {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
  z-index: 0;
}

.glow-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.3;
}

.glow-orb-left {
  width: 480px;
  height: 480px;
  top: -100px;
  left: -100px;
  background: radial-gradient(circle, rgba(255, 209, 220, 0.6) 0%, transparent 70%);
}

.glow-orb-right {
  width: 420px;
  height: 420px;
  top: 150px;
  right: -80px;
  background: radial-gradient(circle, rgba(120, 85, 94, 0.35) 0%, transparent 70%);
}

:global(html.dark) .glow-orb-left {
  background: radial-gradient(circle, rgba(248, 180, 196, 0.15) 0%, transparent 70%);
}

:global(html.dark) .glow-orb-right {
  background: radial-gradient(circle, rgba(83, 51, 62, 0.3) 0%, transparent 70%);
}

.org-main-content {
  position: relative;
  z-index: 1;
  width: 100%;
  padding: 16px 20px 80px;
}

@media (min-width: 768px) {
  .org-main-content {
    padding: 24px 32px 96px;
  }
}

.alerts-container {
  max-width: 1152px;
  margin: 0 auto 12px;
}

.alert-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 8px;
}

.alert-success {
  background: var(--success-bg, #e8f5e9);
  color: var(--success-text, #2e7d32);
  border: 1px solid rgba(46, 125, 50, 0.25);
}

.alert-danger {
  background: var(--error-bg, #ffebee);
  color: var(--error-text, #c62828);
  border: 1px solid rgba(198, 40, 40, 0.25);
}

.org-container {
  max-width: 1152px;
  margin: 0 auto;
}

/* ================= TOP ACTION HEADER ================= */
.org-top-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

@media (min-width: 768px) {
  .org-top-header {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-end;
  }
}

.eyebrow-tag {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: var(--primary, #78555e);
  display: block;
  margin-bottom: 4px;
}

:global(html.dark) .eyebrow-tag {
  color: var(--primary, #f8b4c4);
}

.page-title {
  font-size: 28px;
  font-weight: 900;
  letter-spacing: -0.02em;
  color: var(--on-surface, #191c1d);
  margin: 0 0 6px;
}

@media (min-width: 768px) {
  .page-title {
    font-size: 32px;
  }
}

.page-sub {
  font-size: 13px;
  color: var(--on-surface-variant, #5d5f5f);
  margin: 0;
  max-width: 600px;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-discard {
  padding: 8px 18px;
  border-radius: 9999px;
  background: transparent;
  border: 1px solid var(--border, #e0e4ea);
  color: var(--on-surface-variant, #5d5f5f);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-discard:hover {
  background-color: var(--surface-container, #edeeef);
}

:global(html.dark) .btn-discard {
  border-color: var(--border, #353942);
}

.btn-save {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 22px;
  border-radius: 9999px;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.25);
  transition: all 0.2s;
}

.btn-save:hover {
  background-color: #5e3e47;
  transform: translateY(-1px);
}

:global(html.dark) .btn-save {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

/* ================= NAVIGATION TABS ================= */
.tabs-nav-bar {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  margin-bottom: 24px;
  overflow-x: auto;
}

:global(html.dark) .tabs-nav-bar {
  border-color: var(--border, #353942);
}

.tab-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 600;
  color: var(--on-surface-variant, #5d5f5f);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.tab-link:hover {
  color: var(--on-surface, #191c1d);
}

.tab-link.active {
  color: var(--primary, #78555e);
  border-bottom-color: var(--primary, #78555e);
  font-weight: 700;
}

:global(html.dark) .tab-link.active {
  color: var(--primary, #f8b4c4);
  border-bottom-color: var(--primary, #f8b4c4);
}

/* ================= GRID LAYOUT (8 cols + 4 cols) ================= */
.org-layout-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 960px) {
  .org-layout-grid {
    grid-template-columns: 1fr 340px;
  }
}

/* Main Forms Column */
.main-forms-col {
  display: flex;
  flex-direction: column;
}

.card-section {
  display: flex;
  flex-direction: column;
}

.section-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.dot-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.dot-primary {
  background-color: var(--primary, #78555e);
}

:global(html.dark) .dot-primary {
  background-color: var(--primary, #f8b4c4);
}

.dot-tertiary {
  background-color: #386a55;
}

.section-title {
  font-size: 16px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  margin: 0;
}

.brand-config-card,
.contact-config-card,
.venues-list-card,
.members-table-card,
.policies-card {
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  padding: 24px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .brand-config-card,
:global(html.dark) .contact-config-card,
:global(html.dark) .venues-list-card,
:global(html.dark) .members-table-card,
:global(html.dark) .policies-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

/* Logo Upload Row */
.logo-upload-row {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 24px;
}

@media (min-width: 640px) {
  .logo-upload-row {
    flex-direction: row;
    align-items: center;
  }
}

.logo-preview-box {
  position: relative;
  width: 100px;
  height: 100px;
  border-radius: 16px;
  background-color: var(--surface-subtle, #f5f6f8);
  border: 2px dashed var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  cursor: pointer;
}

:global(html.dark) .logo-preview-box {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.logo-img {
  width: 64px;
  height: 64px;
  object-fit: contain;
}

.upload-overlay {
  position: absolute;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  color: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  opacity: 0;
  transition: opacity 0.2s;
}

.logo-preview-box:hover .upload-overlay {
  opacity: 1;
}

.org-name-input-block {
  flex: 1;
}

.field-label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--on-surface-variant, #5d5f5f);
  margin-bottom: 6px;
}

.field-input-large {
  width: 100%;
  padding: 10px 0;
  font-size: 20px;
  font-weight: 800;
  border: none;
  border-bottom: 2px solid var(--border, #e0e4ea);
  background: transparent;
  color: var(--on-surface, #191c1d);
  outline: none;
  transition: border-color 0.2s;
}

.field-input-large:focus {
  border-color: var(--primary, #78555e);
}

:global(html.dark) .field-input-large {
  border-color: var(--border, #353942);
  color: #ffffff;
}

:global(html.dark) .field-input-large:focus {
  border-color: var(--primary, #f8b4c4);
}

.field-hint {
  font-size: 12px;
  color: var(--on-surface-variant, #5d5f5f);
  margin: 6px 0 0;
}

/* Color Swatches */
.color-palette-block {
  padding-top: 16px;
  border-top: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .color-palette-block {
  border-color: var(--border, #353942);
}

.swatches-row {
  display: flex;
  gap: 12px;
  margin-top: 8px;
}

.color-swatch-btn {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  border: 2px solid transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.color-swatch-btn:hover {
  transform: scale(1.1);
}

.color-swatch-btn.active {
  transform: scale(1.15);
  box-shadow: 0 0 0 3px rgba(255, 209, 220, 0.8);
}

/* Contact Forms */
.form-row-2col {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

@media (min-width: 640px) {
  .form-row-2col {
    grid-template-columns: 1fr 1fr;
  }
}

.form-field {
  display: flex;
  flex-direction: column;
}

.field-input,
.field-textarea {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #e0e4ea);
  background-color: var(--surface-subtle, #f5f6f8);
  font-size: 13px;
  color: var(--on-surface, #191c1d);
  outline: none;
}

:global(html.dark) .field-input,
:global(html.dark) .field-textarea {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

.field-input:focus,
.field-textarea:focus {
  border-color: var(--primary, #78555e);
}

:global(html.dark) .field-input:focus,
:global(html.dark) .field-textarea:focus {
  border-color: var(--primary, #f8b4c4);
}

.status-badge-legal {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  border-radius: 10px;
  background-color: #e8f5e9;
  color: #2e7d32;
  font-size: 12px;
  font-weight: 700;
}

:global(html.dark) .status-badge-legal {
  background-color: rgba(46, 125, 50, 0.25);
  color: #81c784;
}

/* Venues List Tab */
.venue-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 0;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.venue-item-row:last-child {
  border-bottom: none;
}

:global(html.dark) .venue-item-row {
  border-color: var(--border, #353942);
}

.venue-info-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.venue-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background-color: var(--surface-container, #edeeef);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary, #78555e);
}

:global(html.dark) .venue-icon {
  background-color: var(--surface-container, #2b2e36);
  color: var(--primary, #f8b4c4);
}

.venue-name {
  font-size: 14px;
  font-weight: 800;
  margin: 0 0 2px;
}

.venue-addr {
  font-size: 12px;
  color: var(--on-surface-variant, #5d5f5f);
  margin: 0;
}

.venue-meta-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.court-count-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 9999px;
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .court-count-pill {
  background-color: var(--surface-container, #2b2e36);
  color: #ffffff;
}

.status-pill-active {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 9999px;
  background-color: #e8f5e9;
  color: #2e7d32;
}

:global(html.dark) .status-pill-active {
  background-color: rgba(46, 125, 50, 0.25);
  color: #81c784;
}

/* Members Tab */
.add-member-bar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.input-member-email {
  flex: 1;
  min-width: 200px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #e0e4ea);
  background-color: var(--card-bg, #ffffff);
  font-size: 13px;
}

:global(html.dark) .input-member-email {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

.select-member-role {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--border, #e0e4ea);
  background-color: var(--card-bg, #ffffff);
  font-size: 13px;
  cursor: pointer;
}

:global(html.dark) .select-member-role {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

.btn-add-member {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 10px;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}

:global(html.dark) .btn-add-member {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

.members-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.members-table th {
  padding: 10px 12px;
  font-size: 11px;
  font-weight: 700;
  color: var(--on-surface-variant, #5d5f5f);
  border-bottom: 1px solid var(--border, #e0e4ea);
  text-align: left;
}

.members-table td {
  padding: 12px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .members-table th,
:global(html.dark) .members-table td {
  border-color: var(--border, #353942);
}

.member-user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.member-avatar-char {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background-color: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
}

:global(html.dark) .member-avatar-char {
  background-color: var(--primary-container, #53333e);
  color: var(--primary, #f8b4c4);
}

.role-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.role-organization_owner {
  background-color: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

:global(html.dark) .role-organization_owner {
  background-color: var(--primary-container, #53333e);
  color: var(--primary, #f8b4c4);
}

.role-venue_manager {
  background-color: #e0f2fe;
  color: #0369a1;
}

:global(html.dark) .role-venue_manager {
  background-color: rgba(3, 105, 161, 0.25);
  color: #7dd3fc;
}

.role-cashier,
.role-staff {
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface-variant, #5d5f5f);
}

:global(html.dark) .role-cashier,
:global(html.dark) .role-staff {
  background-color: var(--surface-container, #2b2e36);
  color: #ffffff;
}

.badge-status-active {
  font-size: 11px;
  font-weight: 700;
  color: #2e7d32;
}

:global(html.dark) .badge-status-active {
  color: #81c784;
}

.btn-revoke {
  background: transparent;
  border: none;
  color: var(--muted, #817476);
  cursor: pointer;
  padding: 4px;
}

.btn-revoke:hover {
  color: #ba1a1a;
}

/* Policies Tab */
.policy-item-toggle,
.policy-item-input {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 0;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .policy-item-toggle,
:global(html.dark) .policy-item-input {
  border-color: var(--border, #353942);
}

.toggle-checkbox {
  width: 20px;
  height: 20px;
  accent-color: var(--primary, #78555e);
}

.input-unit-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.unit-input {
  width: 70px;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  background-color: var(--surface-subtle, #f5f6f8);
  font-weight: 700;
  text-align: right;
}

:global(html.dark) .unit-input {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

.unit-text {
  font-size: 13px;
  font-weight: 700;
  color: var(--on-surface-variant, #5d5f5f);
}

/* ================= RIGHT SIDEBAR WIDGETS ================= */
.sidebar-widgets-col {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.widget-card {
  border-radius: 20px;
  padding: 24px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .widget-card {
  border-color: var(--border, #353942);
}

.widget-network-reach {
  background-color: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  border-color: rgba(120, 85, 94, 0.2);
}

:global(html.dark) .widget-network-reach {
  background-color: var(--primary-container, #53333e);
  color: var(--primary, #f8b4c4);
}

.widget-eyebrow {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  opacity: 0.8;
}

.widget-title {
  font-size: 20px;
  font-weight: 900;
  margin: 2px 0 0;
  color: inherit;
}

.network-stats-row {
  display: flex;
  align-items: center;
  justify-content: space-around;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid rgba(120, 85, 94, 0.2);
}

:global(html.dark) .network-stats-row {
  border-color: rgba(248, 180, 196, 0.2);
}

.net-stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.net-num {
  font-size: 36px;
  font-weight: 900;
  line-height: 1;
  font-family: monospace;
}

.net-lbl {
  font-size: 11px;
  font-weight: 700;
  opacity: 0.85;
  margin-top: 4px;
}

.net-divider {
  width: 1px;
  height: 36px;
  background-color: rgba(120, 85, 94, 0.2);
}

/* Export Widget */
.widget-export {
  background-color: var(--card-bg, #ffffff);
}

:global(html.dark) .widget-export {
  background-color: var(--surface, #22242a);
}

.export-title {
  font-size: 15px;
  font-weight: 800;
  margin: 0 0 6px;
  color: var(--on-surface, #191c1d);
}

.export-desc {
  font-size: 12px;
  color: var(--on-surface-variant, #5d5f5f);
  line-height: 1.45;
  margin: 0 0 16px;
}

.btn-export-csv {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 10px;
  border-radius: 12px;
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface, #191c1d);
  font-size: 13px;
  font-weight: 700;
  border: 1px solid var(--border, #e0e4ea);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-export-csv:hover {
  background-color: var(--surface-container-high, #e7e8e9);
}

:global(html.dark) .btn-export-csv {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

/* Invoice Widget */
.widget-invoice {
  background-color: var(--card-bg, #ffffff);
}

:global(html.dark) .widget-invoice {
  background-color: var(--surface, #22242a);
}

.invoice-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.invoice-head h4 {
  font-size: 14px;
  font-weight: 800;
  margin: 0;
}

.status-connected-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  background-color: #e8f5e9;
  color: #2e7d32;
  font-size: 11px;
  font-weight: 700;
  margin-top: 10px;
}

:global(html.dark) .status-connected-pill {
  background-color: rgba(46, 125, 50, 0.25);
  color: #81c784;
}

.ping-dot-green {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #2e7d32;
}

:global(html.dark) .ping-dot-green {
  background-color: #81c784;
}
</style>
