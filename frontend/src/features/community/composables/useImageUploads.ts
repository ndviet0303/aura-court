import { ref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { communityApi } from '../api/communityApi'

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const MAX_BYTES = 5 * 1024 * 1024

/** Mirrors the upload rule (jpg/jpeg/png/webp, at most 5 MB): the i18n key of the problem, or null. */
export function imageProblem(file: Pick<File, 'type' | 'size'>): string | null {
  if (!ACCEPTED_TYPES.includes(file.type)) return 'community.images.badType'
  if (file.size > MAX_BYTES) return 'community.images.tooLarge'
  return null
}

/** Uploads picked images one at a time (D9) and keeps their URLs in `images`. */
export function useImageUploads(images: Ref<string[]>, max: number) {
  const { t } = useI18n()
  const uploading = ref(false)
  const error = ref('')

  async function add(files: File[]) {
    error.value = ''
    const room = max - images.value.length
    if (files.length > room) error.value = t('community.images.tooMany', { max })
    uploading.value = true
    try {
      for (const file of files.slice(0, Math.max(room, 0))) {
        const problem = imageProblem(file)
        if (problem) {
          error.value = t(problem, { name: file.name })
          continue
        }
        const { url } = await communityApi.uploadImage(file)
        images.value = [...images.value, url]
      }
    } catch (e) {
      error.value = errorMessage(e, t('community.images.uploadError'))
    } finally {
      uploading.value = false
    }
  }

  function remove(index: number) {
    images.value = images.value.filter((_, i) => i !== index)
  }

  return { uploading, error, add, remove }
}
