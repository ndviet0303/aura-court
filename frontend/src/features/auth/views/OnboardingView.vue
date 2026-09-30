<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/core/utils/errorMessage'
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const accountType = ref<'customer' | 'court_owner'>('customer')
const phone = ref('')
const playLevel = ref('intermediate')
const organizationName = ref('')
const venueAddress = ref('')
const courtsCount = ref<number>(4)

const loading = ref(false)
const error = ref('')

onMounted(async () => {
  if (!auth.loaded) {
    await auth.load()
  }
  if (!auth.user) {
    router.replace({ path: '/login', query: { redirect: '/onboarding' } })
    return
  }
  // If user is already onboarded, skip
  if (auth.user.onboarded) {
    if (auth.user.role === 'admin') {
      router.replace('/admin/organization')
    } else {
      router.replace(String(route.query.redirect || '/'))
    }
  }
})

const playLevels = [
  { value: 'beginner', label: 'Mới bắt đầu', desc: 'Mới làm quen với các bộ môn, chơi giải trí rèn luyện sức khỏe' },
  { value: 'intermediate', label: 'Phong trào', desc: 'Nắm vững luật và kỹ thuật cơ bản, chơi thể thao đều đặn' },
  { value: 'advanced', label: 'Bán chuyên', desc: 'Kỹ thuật tốt, thể lực cao, thường xuyên thi đấu giao lưu' },
  { value: 'pro', label: 'Chuyên nghiệp', desc: 'Thi đấu các giải phong trào và giải đấu chuyên nghiệp' },
]

const isOwner = computed(() => accountType.value === 'court_owner')

async function handleComplete() {
  if (isOwner.value && !organizationName.value.trim()) {
    error.value = 'Vui lòng nhập tên cụm sân / CLB của bạn.'
    return
  }
  if (isOwner.value && !phone.value.trim()) {
    error.value = 'Vui lòng nhập số điện thoại hotline để khách liên hệ.'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const updatedUser = await auth.completeOnboarding({
      account_type: accountType.value,
      phone: phone.value.trim(),
      play_level: isOwner.value ? undefined : playLevel.value,
      organization_name: isOwner.value ? organizationName.value.trim() : undefined,
      venue_address: isOwner.value ? venueAddress.value.trim() : undefined,
      courts_count: isOwner.value ? courtsCount.value : undefined,
    })

    if (updatedUser.role === 'admin') {
      router.push('/admin/organization')
    } else if (isOwner.value) {
      router.push('/partner-registration')
    } else {
      const redirectUrl = String(route.query.redirect || '/')
      router.push(redirectUrl)
    }
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể lưu thông tin. Vui lòng thử lại.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="onboarding-page-wrapper">
    <div class="aura-glow-bg" aria-hidden="true"></div>

    <div class="container onboarding-container">
      <div class="onboarding-card">
        
        <!-- Header -->
        <div class="onboarding-header">
          <div class="welcome-chip">
            <span class="material-symbols-outlined">verified</span>
            <span>XÁC THỰC THÀNH CÔNG</span>
          </div>
          <h1 class="onboarding-title">
            Chào mừng {{ auth.user?.name || 'bạn' }} đến với Aura Court!
          </h1>
          <p class="onboarding-subtitle">
            Hãy chọn vai trò của bạn để chúng tôi tối ưu hóa trải nghiệm phù hợp nhất.
          </p>
        </div>

        <!-- Error Alert -->
        <Transition name="fade-slide">
          <div v-if="error" class="error-banner" role="alert">
            <span class="material-symbols-outlined error-icon">error</span>
            <span class="error-text">{{ error }}</span>
            <button type="button" class="error-close-btn" @click="error = ''">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
        </Transition>

        <!-- Role Selector Grid -->
        <div class="role-grid">
          <!-- Customer Option -->
          <div
            class="role-card"
            :class="{ active: accountType === 'customer' }"
            @click="accountType = 'customer'"
          >
            <div class="role-card-top">
              <div class="role-icon-box">
                <span class="material-symbols-outlined">sports_tennis</span>
              </div>
              <span v-if="accountType === 'customer'" class="material-symbols-outlined check-badge">check_circle</span>
            </div>
            <h3 class="role-title">Khách hàng / Người chơi</h3>
            <p class="role-desc">Đặt sân tức thì, tìm bạn đánh giao lưu, tham gia giải đấu và tích điểm hội viên.</p>
          </div>

          <!-- Court Owner Option -->
          <div
            class="role-card"
            :class="{ active: accountType === 'court_owner' }"
            @click="accountType = 'court_owner'"
          >
            <div class="role-card-top">
              <div class="role-icon-box">
                <span class="material-symbols-outlined">domain</span>
              </div>
              <span v-if="accountType === 'court_owner'" class="material-symbols-outlined check-badge">check_circle</span>
            </div>
            <h3 class="role-title">Chủ sân / Quản lý cụm sân</h3>
            <p class="role-desc">Tự động hóa lịch đặt sân, cấu hình biểu giá động, POS thu ngân và báo cáo doanh thu.</p>
          </div>
        </div>

        <!-- Role Specific Form Fields -->
        <form class="onboarding-form" @submit.prevent="handleComplete">
          
          <!-- CUSTOMER FORM FIELDS -->
          <template v-if="accountType === 'customer'">
            <div class="form-section-title">
              <span class="material-symbols-outlined">person</span>
              <span>Thông tin người chơi</span>
            </div>

            <!-- Phone Number -->
            <div class="input-group">
              <label for="c-phone" class="input-label">Số điện thoại liên hệ (nhận mã đặt sân)</label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">call</span>
                <input
                  id="c-phone"
                  v-model="phone"
                  type="tel"
                  class="custom-input"
                  placeholder="0988 123 456"
                />
              </div>
            </div>

            <!-- Play Level Choice -->
            <div class="input-group">
              <label class="input-label">Trình độ thi đấu của bạn</label>
              <div class="play-level-grid">
                <div
                  v-for="lvl in playLevels"
                  :key="lvl.value"
                  class="play-level-card"
                  :class="{ active: playLevel === lvl.value }"
                  @click="playLevel = lvl.value"
                >
                  <div class="lvl-title">{{ lvl.label }}</div>
                  <div class="lvl-desc">{{ lvl.desc }}</div>
                </div>
              </div>
            </div>
          </template>

          <!-- COURT OWNER FORM FIELDS -->
          <template v-if="accountType === 'court_owner'">
            <div class="form-section-title">
              <span class="material-symbols-outlined">storefront</span>
              <span>Thông tin cơ sở cụm sân</span>
            </div>

            <!-- Club Name -->
            <div class="input-group">
              <label for="o-name" class="input-label">Tên cụm sân / CLB thể thao <span class="required">*</span></label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">domain</span>
                <input
                  id="o-name"
                  v-model="organizationName"
                  type="text"
                  class="custom-input"
                  placeholder="VD: CLB Cầu Lông Aura Star"
                  required
                />
              </div>
            </div>

            <!-- Hotline & Courts Count -->
            <div class="form-row-2">
              <div class="input-group flex-1">
                <label for="o-phone" class="input-label">Số điện thoại hotline/kinh doanh <span class="required">*</span></label>
                <div class="input-wrapper">
                  <span class="material-symbols-outlined input-icon">call</span>
                  <input
                    id="o-phone"
                    v-model="phone"
                    type="tel"
                    class="custom-input"
                    placeholder="0963 792 413"
                    required
                  />
                </div>
              </div>

              <div class="input-group flex-1">
                <label for="o-courts" class="input-label">Số lượng sân hiện có</label>
                <div class="input-wrapper">
                  <span class="material-symbols-outlined input-icon">grid_view</span>
                  <input
                    id="o-courts"
                    v-model.number="courtsCount"
                    type="number"
                    min="1"
                    max="50"
                    class="custom-input"
                    placeholder="4"
                  />
                </div>
              </div>
            </div>

            <!-- Address -->
            <div class="input-group">
              <label for="o-address" class="input-label">Địa chỉ cơ sở cụm sân</label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">location_on</span>
                <input
                  id="o-address"
                  v-model="venueAddress"
                  type="text"
                  class="custom-input"
                  placeholder="Số 123 Đường Cầu Giấy, Hà Nội"
                />
              </div>
            </div>

            <div class="owner-tip-box">
              <span class="material-symbols-outlined">info</span>
              <span>Sau khi hoàn tất, hệ thống sẽ tự động cấu hình tổ chức và chuyển bạn đến trang quản trị để thiết lập bảng giá và phân quyền.</span>
            </div>
          </template>

          <!-- Submit Button -->
          <button
            type="submit"
            class="complete-btn"
            :disabled="loading"
          >
            <span v-if="loading" class="spinner"></span>
            <span v-else class="material-symbols-outlined">
              {{ isOwner ? 'store' : 'sports_tennis' }}
            </span>
            <span>
              {{ isOwner ? 'Hoàn tất & Mở trang quản trị sân' : 'Hoàn tất & Bắt đầu đặt sân' }}
            </span>
          </button>
        </form>

      </div>
    </div>
  </div>
</template>

<style scoped>
.onboarding-page-wrapper {
  position: relative;
  min-height: calc(100vh - 78px);
  padding: 40px 16px 80px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.aura-glow-bg {
  position: absolute;
  top: 10%;
  left: 50%;
  transform: translateX(-50%);
  width: 900px;
  max-width: 90vw;
  height: 500px;
  background: radial-gradient(circle, rgba(201, 161, 90, 0.2) 0%, rgba(107, 45, 63, 0.1) 50%, transparent 70%);
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}

html.dark .aura-glow-bg {
  background: radial-gradient(circle, rgba(107, 45, 63, 0.45) 0%, rgba(223, 186, 114, 0.15) 50%, transparent 70%);
}

.onboarding-container {
  position: relative;
  z-index: 1;
  max-width: 780px;
  width: 100%;
}

.onboarding-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 28px;
  padding: 48px 44px;
  box-shadow: 0 20px 50px -15px rgba(107, 45, 63, 0.08);
}

html.dark .onboarding-card {
  background: #1e181c;
  border-color: #382f35;
  box-shadow: 0 24px 60px -15px rgba(0, 0, 0, 0.6);
}

.onboarding-header {
  text-align: center;
  margin-bottom: 32px;
}

.welcome-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(201, 161, 90, 0.15);
  color: #8f651b;
  border: 1px solid rgba(201, 161, 90, 0.35);
  padding: 5px 14px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 800;
  letter-spacing: 0.08em;
  margin-bottom: 14px;
}

html.dark .welcome-chip {
  background: rgba(223, 186, 114, 0.18);
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.35);
}

.onboarding-title {
  font-size: clamp(24px, 2.5vw, 30px);
  font-weight: 800;
  color: var(--ink);
  margin: 0 0 8px;
  letter-spacing: -0.02em;
}

.onboarding-subtitle {
  font-size: 14.5px;
  color: var(--muted);
  margin: 0 auto;
  max-width: 540px;
  line-height: 1.55;
}

/* Error Banner */
.error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: rgba(186, 26, 26, 0.08);
  border: 1px solid rgba(186, 26, 26, 0.25);
  border-radius: 12px;
  color: #ba1a1a;
  margin-bottom: 24px;
  font-size: 13.5px;
}

html.dark .error-banner {
  background: rgba(255, 218, 214, 0.12);
  border-color: rgba(255, 218, 214, 0.25);
  color: #ffb4ab;
}

.error-close-btn {
  background: transparent;
  border: none;
  color: inherit;
  cursor: pointer;
  display: flex;
}

/* Role Grid */
.role-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 32px;
}

.role-card {
  background: var(--surface-subtle);
  border: 2px solid var(--border);
  border-radius: 18px;
  padding: 22px 20px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
}

.role-card:hover {
  border-color: #6B2D3F;
  transform: translateY(-2px);
}

html.dark .role-card:hover {
  border-color: #dfba72;
}

.role-card.active {
  border-color: #6B2D3F;
  background: var(--surface);
  box-shadow: 0 6px 20px rgba(107, 45, 63, 0.15);
}

html.dark .role-card.active {
  border-color: #dfba72;
  background: #251f23;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.role-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.role-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6B2D3F;
}

html.dark .role-icon-box {
  color: #dfba72;
  background: #201a1e;
  border-color: #382f35;
}

.check-badge {
  font-size: 22px;
  color: #16a34a;
}

html.dark .check-badge {
  color: #4ade80;
}

.role-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 6px;
}

.role-desc {
  font-size: 13px;
  color: var(--muted);
  line-height: 1.5;
  margin: 0;
}

/* Form Styles */
.onboarding-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}

.form-section-title .material-symbols-outlined {
  color: #6B2D3F;
}

html.dark .form-section-title .material-symbols-outlined {
  color: #dfba72;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.input-label {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--ink);
}

.required {
  color: #ba1a1a;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 14px;
  color: var(--muted);
  font-size: 20px;
  pointer-events: none;
}

.custom-input {
  width: 100%;
  height: 48px;
  padding: 0 16px 0 46px;
  font-size: 14.5px;
  color: var(--ink);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  outline: none;
  transition: all 0.2s ease;
}

html.dark .custom-input {
  background: #251f23;
  border-color: #382f35;
  color: #ffffff;
}

.custom-input:focus {
  border-color: #6B2D3F;
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.15);
}

html.dark .custom-input:focus {
  border-color: #dfba72;
  box-shadow: 0 0 0 3px rgba(223, 186, 114, 0.2);
}

.form-row-2 {
  display: flex;
  gap: 16px;
}

.flex-1 {
  flex: 1;
}

/* Play Levels */
.play-level-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.play-level-card {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.play-level-card:hover {
  border-color: #6B2D3F;
}

html.dark .play-level-card:hover {
  border-color: #dfba72;
}

.play-level-card.active {
  border-color: #6B2D3F;
  background: var(--surface);
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.12);
}

html.dark .play-level-card.active {
  border-color: #dfba72;
  background: #251f23;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.lvl-title {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 2px;
}

.lvl-desc {
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.4;
}

/* Owner Tip Box */
.owner-tip-box {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: rgba(201, 161, 90, 0.1);
  border: 1px solid rgba(201, 161, 90, 0.25);
  border-radius: 12px;
  padding: 12px 14px;
  font-size: 12.5px;
  color: #8f651b;
  line-height: 1.5;
}

html.dark .owner-tip-box {
  background: rgba(223, 186, 114, 0.12);
  border-color: rgba(223, 186, 114, 0.25);
  color: #dfba72;
}

.owner-tip-box .material-symbols-outlined {
  font-size: 18px;
  margin-top: 1px;
  flex-shrink: 0;
}

/* Complete Button */
.complete-btn {
  margin-top: 12px;
  height: 52px;
  background: linear-gradient(135deg, #6B2D3F 0%, #52212F 100%);
  color: #ffffff;
  border: none;
  border-radius: 14px;
  font-size: 15.5px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 6px 20px rgba(107, 45, 63, 0.35);
}

.complete-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(107, 45, 63, 0.45);
  filter: brightness(1.08);
}

.complete-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.spinner {
  width: 20px;
  height: 20px;
  border: 2.5px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: #ffffff;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
  .onboarding-card {
    padding: 32px 20px;
    border-radius: 20px;
  }

  .role-grid,
  .play-level-grid,
  .form-row-2 {
    grid-template-columns: 1fr;
    flex-direction: column;
  }
}
</style>
