import { ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { errorMessage } from '@/core/utils/errorMessage'
import { communityApi } from '../api/communityApi'
import type { Comment, Post } from '../types/community'

/** FR-113: one post with its comments; a hidden or deleted post answers 404. */
export function usePostDetail(id: Ref<number>) {
  const { t } = useI18n()
  const router = useRouter()
  const post = ref<Post | null>(null)
  const comments = ref<Comment[]>([])
  const loading = ref(false)
  const sending = ref(false)
  const error = ref('')

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const { post: loaded } = await communityApi.post(id.value)
      const { comments: list, ...rest } = loaded
      post.value = rest
      comments.value = list
    } catch (e) {
      post.value = null
      error.value = errorMessage(e, t('community.loadError'))
    } finally {
      loading.value = false
    }
  }
  watch(id, load, { immediate: true })

  function setCount(delta: number) {
    if (post.value)
      post.value = { ...post.value, comments_count: post.value.comments_count + delta }
  }

  async function addComment(content: string): Promise<boolean> {
    sending.value = true
    error.value = ''
    try {
      const { comment } = await communityApi.addComment(id.value, content)
      comments.value = [...comments.value, comment]
      setCount(1)
      return true
    } catch (e) {
      error.value = errorMessage(e, t('community.comment.saveError'))
      return false
    } finally {
      sending.value = false
    }
  }

  async function editComment(target: Comment, content: string): Promise<boolean> {
    error.value = ''
    try {
      const { comment } = await communityApi.updateComment(target.id, content)
      comments.value = comments.value.map((c) => (c.id === comment.id ? comment : c))
      return true
    } catch (e) {
      error.value = errorMessage(e, t('community.comment.saveError'))
      return false
    }
  }

  async function removeComment(target: Comment) {
    if (!window.confirm(t('community.comment.deleteConfirm'))) return
    error.value = ''
    try {
      await communityApi.removeComment(target.id)
      comments.value = comments.value.filter((c) => c.id !== target.id)
      setCount(-1)
    } catch (e) {
      error.value = errorMessage(e, t('community.comment.deleteError'))
    }
  }

  async function removePost() {
    if (!post.value || !window.confirm(t('community.post.deleteConfirm'))) return
    try {
      await communityApi.remove(post.value.id)
      await router.push('/community')
    } catch (e) {
      error.value = errorMessage(e, t('community.post.deleteError'))
    }
  }

  return {
    post,
    comments,
    loading,
    sending,
    error,
    addComment,
    editComment,
    removeComment,
    removePost,
  }
}
