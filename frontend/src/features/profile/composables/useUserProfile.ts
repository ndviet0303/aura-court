import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function useUserProfile() {
  const auth = useAuthStore()
  const activeTab = ref<'general' | 'security' | 'membership' | 'shortcuts'>('general')
  const notice = ref('')
  const error = ref('')

  // Form states
  const name = ref(auth.user?.name || '')
  const phone = ref(auth.user?.phone || '')
  const playLevel = ref(auth.user?.play_level || 'intermediate')

  // Security form states
  const currentPassword = ref('')
  const newPassword = ref('')
  const confirmPassword = ref('')

  const userInitials = computed(() => {
    if (!auth.user?.name) return 'AU'
    const parts = auth.user.name.trim().split(' ')
    if (parts.length === 1) return (parts[0]?.slice(0, 2) || '').toUpperCase()
    return ((parts[0]?.[0] || '') + (parts[parts.length - 1]?.[0] || '')).toUpperCase()
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function saveProfile() {
    if (auth.user) {
      auth.user.name = name.value
      auth.user.phone = phone.value
      auth.user.play_level = playLevel.value as any
    }
    showNotice('Cập nhật hồ sơ cá nhân thành công!')
  }

  function updatePassword() {
    if (!newPassword.value) return
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    showNotice('Đổi mật khẩu bảo mật thành công!')
  }

  return {
    auth,
    activeTab,
    notice,
    error,
    name,
    phone,
    playLevel,
    currentPassword,
    newPassword,
    confirmPassword,
    userInitials,
    showNotice,
    saveProfile,
    updatePassword,
  }
}
