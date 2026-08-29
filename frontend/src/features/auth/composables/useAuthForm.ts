import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/core/utils/errorMessage'

export function useAuthForm() {
  const router = useRouter()
  const auth = useAuthStore()

  const mode = ref<'login' | 'register'>('login')
  const loading = ref(false)
  const error = ref('')
  const notice = ref('')

  // Login form
  const loginEmail = ref('')
  const loginPassword = ref('')
  const rememberMe = ref(true)

  // Register form
  const regName = ref('')
  const regEmail = ref('')
  const regPassword = ref('')
  const regPasswordConfirmation = ref('')

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function handleLogin() {
    loading.value = true
    error.value = ''
    try {
      await auth.login(loginEmail.value.trim(), loginPassword.value)
      router.push('/')
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Email hoặc mật khẩu không chính xác.')
    } finally {
      loading.value = false
    }
  }

  async function handleRegister() {
    if (regPassword.value !== regPasswordConfirmation.value) {
      error.value = 'Mật khẩu xác nhận không khớp.'
      return
    }
    loading.value = true
    error.value = ''
    try {
      await auth.register(
        regName.value.trim(),
        regEmail.value.trim(),
        regPassword.value,
        regPasswordConfirmation.value
      )
      router.push('/onboarding')
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Đăng ký không thành công. Vui lòng thử lại.')
    } finally {
      loading.value = false
    }
  }

  return {
    mode,
    loading,
    error,
    notice,
    loginEmail,
    loginPassword,
    rememberMe,
    regName,
    regEmail,
    regPassword,
    regPasswordConfirmation,
    handleLogin,
    handleRegister,
  }
}
