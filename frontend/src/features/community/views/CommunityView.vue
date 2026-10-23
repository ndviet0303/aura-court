<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { useStartChat } from '@/features/chat/composables/useStartChat'
import { AuraButton } from '@/shared/components'
import { useCommunityFeed } from '../composables/useCommunityFeed'
import type { Post, PostType, ReportTarget } from '../types/community'
import LoginPrompt from '../components/LoginPrompt.vue'
import PostCard from '../components/PostCard.vue'
import PostComposer from '../components/PostComposer.vue'
import ReportDialog from '../components/ReportDialog.vue'

// FR-112..FR-114: guests read the feed; posting, commenting and reporting need an account.
const { t } = useI18n()
const auth = useAuthStore()
const signedIn = computed(() => !!auth.user)
const { type, posts, loading, error, hasMore, loadMore, upsert, remove } = useCommunityFeed()
const startChat = useStartChat()

const FILTERS: (PostType | '')[] = ['', 'share', 'matchmaking']
const composerKey = ref(0)
const editingId = ref<number | null>(null)
const reporting = ref<ReportTarget | null>(null)
const chatError = ref('')

function created(post: Post) {
  upsert(post)
  composerKey.value++
}

function edited(post: Post) {
  upsert(post)
  editingId.value = null
}

async function message(post: Post) {
  chatError.value = (await startChat({ type: 'customer_customer', user_id: post.author.id })) ?? ''
}
</script>

<template>
  <div class="community-page">
    <header>
      <h1 class="community-title">{{ t('community.title') }}</h1>
      <p class="community-sub">{{ t('community.subtitle') }}</p>
    </header>

    <section class="community-card">
      <PostComposer v-if="signedIn" :key="composerKey" @saved="created" />
      <LoginPrompt v-else :text="t('community.loginToPost')" />
    </section>

    <div class="community-filters" role="tablist" :aria-label="t('community.filterLabel')">
      <button
        v-for="filter in FILTERS"
        :key="filter"
        type="button"
        role="tab"
        class="community-filter"
        :class="{ active: type === filter }"
        :aria-selected="type === filter"
        @click="type = filter"
      >
        {{ filter ? t(`community.types.${filter}`) : t('common.all') }}
      </button>
    </div>

    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <p v-if="chatError" class="form-error" role="alert">{{ chatError }}</p>
    <p v-if="!loading && !posts.length && !error" class="community-sub">
      {{ t('community.empty') }}
    </p>

    <template v-for="post in posts" :key="post.id">
      <section v-if="editingId === post.id" class="community-card">
        <PostComposer :post="post" @saved="edited" @cancel="editingId = null" />
      </section>
      <PostCard
        v-else
        :post="post"
        :signed-in="signedIn"
        linked
        @edit="editingId = post.id"
        @delete="remove(post)"
        @report="reporting = { type: 'post', id: post.id }"
        @message="message(post)"
      />
    </template>

    <AuraButton v-if="hasMore || loading" variant="outline" :loading="loading" @click="loadMore">
      {{ t('community.loadMore') }}
    </AuraButton>

    <ReportDialog
      v-if="reporting"
      :target="reporting"
      @close="reporting = null"
      @done="reporting = null"
    />
  </div>
</template>

<style scoped>
.community-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 760px;
  margin: 0 auto;
  padding: 24px 16px 60px;
  color: var(--ink);
}

.community-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.community-sub {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13.5px;
}

.community-card {
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.community-filters {
  display: flex;
  gap: 8px;
}

.community-filter {
  padding: 6px 14px;
  border: 1px solid var(--outline);
  border-radius: 999px;
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  cursor: pointer;
}

.community-filter.active {
  border-color: var(--primary, #6b2d3f);
  background: var(--primary, #6b2d3f);
  color: var(--on-primary, #fff);
  font-weight: 700;
}
</style>
