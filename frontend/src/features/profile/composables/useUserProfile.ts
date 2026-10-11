import { ref, computed, watch, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { profileApi } from '../api/profileApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { getAvatarUrl, getUserInitials } from '@/lib/avatar'
import type { ProfileTabType } from '../types/profile'
import type { Membership } from '@/core/types/common'

export function useUserProfile() {
  const auth = useAuthStore()
  const activeTab = ref<ProfileTabType>('general')
  const notice = ref('')
  const error = ref('')
  const loading = ref(false)
  const savingProfile = ref(false)
  const savingPassword = ref(false)
  const resendingVerification = ref(false)
  const membership = ref<Membership | null>(null)

  // Profile Form States
  const name = ref(auth.user?.name || '')
  const phone = ref(auth.user?.phone || '')
  const playLevel = ref(auth.user?.play_level || 'intermediate')
  const favoriteSports = ref<string[]>(
    Array.isArray(auth.user?.favorite_sports) ? auth.user.favorite_sports : ['badminton'],
  )
  const avatarUrl = ref(auth.user?.avatar_url || '')

  // Password Form States
  const currentPassword = ref('')
  const newPassword = ref('')
  const confirmPassword = ref('')

  // Keep form synced when auth.user loads or updates
  watch(
    () => auth.user,
    (user) => {
      if (!user) return
      name.value = user.name || ''
      phone.value = user.phone || ''
      playLevel.value = user.play_level || 'intermediate'
      favoriteSports.value = Array.isArray(user.favorite_sports)
        ? user.favorite_sports
        : ['badminton']
      avatarUrl.value = user.avatar_url || ''
    },
    { immediate: true },
  )

  const userInitials = computed(() => getUserInitials(name.value || auth.user?.name))
  const displayAvatar = computed(() =>
    getAvatarUrl(name.value || auth.user?.name, avatarUrl.value || auth.user?.avatar_url),
  )

  const isEmailVerified = computed(() => !!auth.user?.email_verified_at)
  const isSocialUser = computed(() => !!auth.user?.social_provider)
  const hasPasswordSet = computed(() => auth.user?.password_set !== false)

  function showNotice(msg: string) {
    notice.value = msg
    error.value = ''
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function showError(msg: string) {
    error.value = msg
    notice.value = ''
    setTimeout(() => {
      if (error.value === msg) error.value = ''
    }, 5000)
  }

  async function loadMembership() {
    try {
      const res = await profileApi.getMembership()
      membership.value = res.membership || null
    } catch {
      // Non-blocking fallback
      membership.value = null
    }
  }

  async function saveProfile() {
    if (!name.value.trim()) {
      showError('Vui lòng nhập họ và tên hiển thị.')
      return
    }

    savingProfile.value = true
    error.value = ''
    try {
      const updatedUser = await profileApi.updateProfile({
        name: name.value.trim(),
        phone: phone.value.trim() || undefined,
        play_level: playLevel.value,
        favorite_sports: favoriteSports.value,
        avatar_url: avatarUrl.value.trim() || undefined,
      })

      if (auth.user) {
        Object.assign(auth.user, updatedUser)
      }
      showNotice('Cập nhật thông tin hồ sơ thành công.')
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể cập nhật hồ sơ cá nhân. Vui lòng thử lại.'))
    } finally {
      savingProfile.value = false
    }
  }

  async function updatePassword() {
    if (!newPassword.value) {
      showError('Vui lòng nhập mật khẩu mới.')
      return
    }
    if (newPassword.value.length < 8) {
      showError('Mật khẩu mới phải có độ dài tối thiểu 8 ký tự.')
      return
    }
    if (newPassword.value !== confirmPassword.value) {
      showError('Xác nhận mật khẩu mới không trùng khớp.')
      return
    }
    if (hasPasswordSet.value && !currentPassword.value) {
      showError('Vui lòng nhập mật khẩu hiện tại.')
      return
    }

    savingPassword.value = true
    error.value = ''
    try {
      const res = await profileApi.changePassword({
        current_password: currentPassword.value,
        password: newPassword.value,
        password_confirmation: confirmPassword.value,
      })

      if (auth.user) {
        auth.user.password_set = true
        if (res.user) {
          Object.assign(auth.user, res.user)
        }
      }

      currentPassword.value = ''
      newPassword.value = ''
      confirmPassword.value = ''
      showNotice(res.message || 'Cập nhật mật khẩu bảo mật thành công.')
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể cập nhật mật khẩu. Vui lòng kiểm tra lại.'))
    } finally {
      savingPassword.value = false
    }
  }

  async function resendVerification() {
    resendingVerification.value = true
    error.value = ''
    try {
      const res = await profileApi.resendVerification()
      showNotice(res.message || 'Đã gửi liên kết xác thực đến hộp thư email của bạn.')
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể gửi lại email xác thực. Vui lòng thử lại sau.'))
    } finally {
      resendingVerification.value = false
    }
  }

  onMounted(async () => {
    await Promise.allSettled([auth.load(true), loadMembership()])
  })

  return {
    auth,
    activeTab,
    notice,
    error,
    loading,
    savingProfile,
    savingPassword,
    resendingVerification,
    membership,
    name,
    phone,
    playLevel,
    favoriteSports,
    avatarUrl,
    currentPassword,
    newPassword,
    confirmPassword,
    userInitials,
    displayAvatar,
    isEmailVerified,
    isSocialUser,
    hasPasswordSet,
    showNotice,
    showError,
    saveProfile,
    updatePassword,
    resendVerification,
    loadMembership,
  }
}
