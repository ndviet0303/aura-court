<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { useStartChat } from '@/features/chat/composables/useStartChat'
import { usePostDetail } from '../composables/usePostDetail'
import type { Post, ReportTarget } from '../types/community'
import CommentSection from '../components/CommentSection.vue'
import PostCard from '../components/PostCard.vue'
import PostComposer from '../components/PostComposer.vue'
import ReportDialog from '../components/ReportDialog.vue'

// FR-113: a post with its comments; notifications (community.comment) link here.
const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const signedIn = computed(() => !!auth.user)
const id = computed(() => Number(route.params.id))
const detail = usePostDetail(id)
const { post, comments, loading, sending, error } = detail
const startChat = useStartChat()

const editing = ref(false)
const reporting = ref<ReportTarget | null>(null)
const chatError = ref('')

function edited(saved: Post) {
  post.value = saved
  editing.value = false
}

async function message() {
  if (!post.value) return
  chatError.value =
    (await startChat({ type: 'customer_customer', user_id: post.value.author.id })) ?? ''
}
</script>

<template>
  <div class="post-page">
    <RouterLink to="/community" class="admin-detail-back">← {{ t('community.back') }}</RouterLink>
    <p v-if="loading && !post" class="post-muted">{{ t('common.loading') }}</p>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <p v-if="chatError" class="form-error" role="alert">{{ chatError }}</p>

    <template v-if="post">
      <section v-if="editing" class="post-edit">
        <PostComposer :post="post" @saved="edited" @cancel="editing = false" />
      </section>
      <PostCard
        v-else
        :post="post"
        :signed-in="signedIn"
        @edit="editing = true"
        @delete="detail.removePost"
        @report="reporting = { type: 'post', id: post.id }"
        @message="message"
      />

      <CommentSection
        :comments="comments"
        :signed-in="signedIn"
        :sending="sending"
        @add="async (content, done) => done(await detail.addComment(content))"
        @save="async (comment, content, done) => done(await detail.editComment(comment, content))"
        @delete="detail.removeComment"
        @report="(comment) => (reporting = { type: 'comment', id: comment.id })"
      />
    </template>

    <ReportDialog
      v-if="reporting"
      :target="reporting"
      @close="reporting = null"
      @done="reporting = null"
    />
  </div>
</template>

<style scoped>
.post-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 760px;
  margin: 0 auto;
  padding: 24px 16px 60px;
  color: var(--ink);
}

.post-muted {
  margin: 0;
  color: var(--muted);
}

.post-edit {
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}
</style>
