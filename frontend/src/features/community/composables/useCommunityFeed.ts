import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { communityApi } from '../api/communityApi'
import type { Post, PostType } from '../types/community'

/** FR-112: the public feed, filtered by post type and loaded page by page. */
export function useCommunityFeed() {
  const { t } = useI18n()
  const type = ref<PostType | ''>('')
  const posts = ref<Post[]>([])
  const page = ref(1)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref('')
  let latest = 0

  async function load(target = 1) {
    const request = ++latest
    loading.value = true
    error.value = ''
    try {
      const res = await communityApi.feed(type.value, target)
      if (request !== latest) return
      posts.value = target === 1 ? res.data : [...posts.value, ...res.data]
      page.value = res.meta.current_page
      lastPage.value = res.meta.last_page
    } catch (e) {
      if (request === latest) error.value = errorMessage(e, t('community.loadError'))
    } finally {
      if (request === latest) loading.value = false
    }
  }
  watch(type, () => load(1), { immediate: true })

  const hasMore = computed(() => page.value < lastPage.value)

  /** A new post goes on top; an edited one is replaced where it is. */
  function upsert(post: Post) {
    const index = posts.value.findIndex((p) => p.id === post.id)
    if (index === -1) posts.value = [post, ...posts.value]
    else posts.value = posts.value.map((p) => (p.id === post.id ? post : p))
  }

  async function remove(post: Post) {
    if (!window.confirm(t('community.post.deleteConfirm'))) return
    try {
      await communityApi.remove(post.id)
      posts.value = posts.value.filter((p) => p.id !== post.id)
    } catch (e) {
      error.value = errorMessage(e, t('community.post.deleteError'))
    }
  }

  return {
    type,
    posts,
    loading,
    error,
    hasMore,
    loadMore: () => load(page.value + 1),
    upsert,
    remove,
  }
}
