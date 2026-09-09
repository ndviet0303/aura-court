<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { socialApi } from '../api/socialApi'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/core/utils/errorMessage'

const auth = useAuthStore()

const loading = ref(false)
const posts = ref<any[]>([])
const newPostContent = ref('')
const newPostAudience = ref<'organization' | 'friends'>('organization')
const submittingPost = ref(false)
const notice = ref('')
const error = ref('')

// Comment inputs by postId
const commentInputs = ref<Record<number, string>>({})
const submittingComment = ref<Record<number, boolean>>({})

// Report dialog state
const reportingItem = ref<{ type: 'post' | 'comment'; id: number } | null>(null)
const reportReason = ref('')
const submittingReport = ref(false)

async function loadFeed() {
  loading.value = true
  error.value = ''
  try {
    const res = await socialApi.getFeed()
    posts.value = res?.data || []
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể tải bảng tin cộng đồng.')
  } finally {
    loading.value = false
  }
}

async function handleCreatePost() {
  if (!newPostContent.value.trim()) return
  submittingPost.value = true
  error.value = ''
  notice.value = ''

  try {
    const res = await socialApi.createFeedPost({
      content: newPostContent.value.trim(),
      audience: newPostAudience.value,
    })
    notice.value = res?.message || 'Đăng bài thành công!'
    newPostContent.value = ''
    await loadFeed()
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể đăng bài viết.')
  } finally {
    submittingPost.value = false
  }
}

async function handleDeletePost(postId: number) {
  try {
    await socialApi.deleteFeedPost(postId)
    posts.value = posts.value.filter((p) => p.id !== postId)
    notice.value = 'Đã xóa bài viết.'
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể xóa bài viết.')
  }
}

async function handleAddComment(postId: number) {
  const text = commentInputs.value[postId]?.trim()
  if (!text) return

  submittingComment.value[postId] = true
  try {
    const res = await socialApi.addFeedComment(postId, text)
    const post = posts.value.find((p) => p.id === postId)
    if (post) {
      if (!post.comments) post.comments = []
      post.comments.push(res.comment)
      post.comments_count = (post.comments_count || 0) + 1
    }
    commentInputs.value[postId] = ''
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể gửi bình luận.')
  } finally {
    submittingComment.value[postId] = false
  }
}

async function handleDeleteComment(postId: number, commentId: number) {
  try {
    await socialApi.deleteFeedComment(commentId)
    const post = posts.value.find((p) => p.id === postId)
    if (post && post.comments) {
      post.comments = post.comments.filter((c: any) => c.id !== commentId)
      post.comments_count = Math.max(0, (post.comments_count || 1) - 1)
    }
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể xóa bình luận.')
  }
}

async function handleReact(type: 'post' | 'comment', id: number, reactionType: string) {
  try {
    const res = await socialApi.reactFeedItem(type, id, reactionType)
    if (type === 'post') {
      const post = posts.value.find((p) => p.id === id)
      if (post) {
        post.reactions_counts = (res as any)?.counts || {}
      }
    }
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể thả biểu cảm.')
  }
}

function openReport(type: 'post' | 'comment', id: number) {
  reportingItem.value = { type, id }
  reportReason.value = ''
}

async function submitReport() {
  if (!reportingItem.value || !reportReason.value.trim()) return
  submittingReport.value = true
  try {
    await socialApi.reportFeedItem(reportingItem.value.type, reportingItem.value.id, reportReason.value.trim())
    notice.value = 'Đã gửi báo cáo vi phạm tới ban quản trị.'
    reportingItem.value = null
    reportReason.value = ''
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể gửi báo cáo vi phạm.')
  } finally {
    submittingReport.value = false
  }
}

onMounted(() => {
  loadFeed()
})
</script>

<template>
  <div class="community-feed-container">
    <!-- Notice & Error Alerts -->
    <div v-if="notice" class="feed-alert-pill success">{{ notice }}</div>
    <div v-if="error" class="feed-alert-pill error">{{ error }}</div>

    <!-- Create Post Composer -->
    <div class="feed-composer-card">
      <div class="composer-head">
        <span class="material-symbols-outlined head-ico">edit_note</span>
        <h3>Bảng Tin Hoạt Động & Giao Lưu</h3>
      </div>
      <textarea
        v-model="newPostContent"
        class="feed-textarea"
        placeholder="Chia sẻ khoảnh khắc sân thể thao, tìm bạn giao lưu hoặc thảo luận..."
        rows="3"
        maxlength="2000"
      ></textarea>

      <div class="composer-actions-row">
        <div class="audience-selector">
          <label class="audience-label">
            <span class="material-symbols-outlined audience-ico">
              {{ newPostAudience === 'organization' ? 'public' : 'lock' }}
            </span>
            <select v-model="newPostAudience" class="audience-select">
              <option value="organization">Công khai toàn CLB (Organization)</option>
              <option value="friends">Chỉ bạn bè (Friends-only)</option>
            </select>
          </label>
        </div>

        <button
          type="button"
          class="btn-publish-post"
          :disabled="!newPostContent.trim() || submittingPost"
          @click="handleCreatePost"
        >
          <span class="material-symbols-outlined btn-ico">send</span>
          <span>{{ submittingPost ? 'Đang đăng...' : 'Đăng bài' }}</span>
        </button>
      </div>
    </div>

    <!-- Feed Loading State -->
    <div v-if="loading" class="feed-loading-state">
      <div class="spinner"></div>
      <p>Đang tải bài viết từ cộng đồng...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="posts.length === 0" class="feed-empty-state">
      <span class="material-symbols-outlined empty-ico">forum</span>
      <p>Chưa có bài viết nào trên bảng tin. Hãy là người đầu tiên chia sẻ!</p>
    </div>

    <!-- Posts Stream -->
    <div v-else class="feed-posts-stream">
      <div v-for="post in posts" :key="post.id" class="feed-post-card">
        <!-- Post Header -->
        <div class="post-header">
          <div class="author-info">
            <div class="author-avatar font-mono">
              {{ (post.user?.name || 'A').charAt(0).toUpperCase() }}
            </div>
            <div class="author-meta">
              <strong class="author-name">{{ post.user?.name || 'Thành viên CLB' }}</strong>
              <div class="post-subline">
                <span class="audience-tag" :class="post.audience">
                  <span class="material-symbols-outlined tag-ico">
                    {{ post.audience === 'friends' ? 'lock' : 'public' }}
                  </span>
                  {{ post.audience === 'friends' ? 'Chỉ bạn bè' : 'Toàn CLB' }}
                </span>
                <span class="post-time">{{ new Date(post.created_at).toLocaleDateString() }}</span>
              </div>
            </div>
          </div>

          <div class="post-menu-actions">
            <button
              v-if="post.user_id === auth.user?.id"
              type="button"
              class="btn-icon-del"
              title="Xóa bài viết"
              @click="handleDeletePost(post.id)"
            >
              <span class="material-symbols-outlined">delete</span>
            </button>
            <button
              v-else
              type="button"
              class="btn-icon-report"
              title="Báo cáo vi phạm"
              @click="openReport('post', post.id)"
            >
              <span class="material-symbols-outlined">flag</span>
            </button>
          </div>
        </div>

        <!-- Post Body -->
        <div class="post-content">
          {{ post.content }}
        </div>

        <!-- Reactions Bar -->
        <div class="post-reactions-bar">
          <button
            type="button"
            class="btn-reaction"
            @click="handleReact('post', post.id, 'like')"
          >
            👍 Thích
            <span v-if="post.reactions_counts?.like" class="count-badge">
              {{ post.reactions_counts.like }}
            </span>
          </button>
          <button
            type="button"
            class="btn-reaction"
            @click="handleReact('post', post.id, 'love')"
          >
            ❤️ Thả tim
            <span v-if="post.reactions_counts?.love" class="count-badge">
              {{ post.reactions_counts.love }}
            </span>
          </button>
          <button
            type="button"
            class="btn-reaction"
            @click="handleReact('post', post.id, 'fire')"
          >
            🔥 Chiến
            <span v-if="post.reactions_counts?.fire" class="count-badge">
              {{ post.reactions_counts.fire }}
            </span>
          </button>
          <button
            type="button"
            class="btn-reaction"
            @click="handleReact('post', post.id, 'celebrate')"
          >
            🎉 Chúc mừng
            <span v-if="post.reactions_counts?.celebrate" class="count-badge">
              {{ post.reactions_counts.celebrate }}
            </span>
          </button>
        </div>

        <!-- Comments Section -->
        <div class="comments-section">
          <div v-if="post.comments?.length > 0" class="comments-list">
            <div v-for="c in post.comments" :key="c.id" class="comment-item">
              <div class="comment-bubble">
                <strong class="comment-author">{{ c.user?.name || 'Thành viên' }}:</strong>
                <span class="comment-text">{{ c.content }}</span>
              </div>
              <button
                v-if="c.user_id === auth.user?.id"
                type="button"
                class="btn-comment-del"
                @click="handleDeleteComment(post.id, c.id)"
              >
                <span class="material-symbols-outlined icon-16">close</span>
              </button>
            </div>
          </div>

          <!-- Add Comment Box -->
          <div class="add-comment-row">
            <input
              v-model="commentInputs[post.id]"
              type="text"
              class="comment-input"
              placeholder="Viết bình luận..."
              maxlength="1000"
              @keyup.enter="handleAddComment(post.id)"
            />
            <button
              type="button"
              class="btn-send-comment"
              :disabled="!commentInputs[post.id]?.trim() || submittingComment[post.id]"
              @click="handleAddComment(post.id)"
            >
              <span class="material-symbols-outlined">send</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Report Modal -->
    <div v-if="reportingItem" class="modal-backdrop" @click="reportingItem = null">
      <div class="modal-card" @click.stop>
        <h3>Báo Cáo Nội Dung Vi Phạm</h3>
        <p class="modal-sub">Gửi báo cáo tới ban điều hành để kiểm duyệt và xử lý vi phạm.</p>
        <textarea
          v-model="reportReason"
          class="report-textarea"
          placeholder="Lý do báo cáo (spam, xúc phạm, thông tin sai lệch)..."
          rows="3"
          maxlength="500"
        ></textarea>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" @click="reportingItem = null">Hủy</button>
          <button
            type="button"
            class="btn-confirm-report"
            :disabled="!reportReason.trim() || submittingReport"
            @click="submitReport"
          >
            {{ submittingReport ? 'Đang gửi...' : 'Gửi báo cáo' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.community-feed-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  max-width: 800px;
  margin: 0 auto;
}
.feed-alert-pill {
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 13.5px;
  font-weight: 500;
}
.feed-alert-pill.success {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
}
.feed-alert-pill.error {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
}
.feed-composer-card,
.feed-post-card {
  background: var(--surface, #1e293b);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 14px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.composer-head {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 15px;
  color: var(--ink, #fff);
}
.head-ico {
  color: #3b82f6;
  font-size: 20px;
}
.feed-textarea {
  width: 100%;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 10px;
  padding: 12px;
  color: #fff;
  font-size: 14px;
  resize: vertical;
}
.composer-actions-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.audience-label {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.05);
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 13px;
  color: #94a3b8;
}
.audience-select {
  background: transparent;
  border: none;
  color: #fff;
  font-size: 13px;
  cursor: pointer;
}
.btn-publish-post {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #2563eb;
  color: #fff;
  border: none;
  padding: 8px 18px;
  border-radius: 8px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-publish-post:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.feed-posts-stream {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.post-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.author-info {
  display: flex;
  align-items: center;
  gap: 12px;
}
.author-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #3b82f6;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
}
.author-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.author-name {
  font-size: 14.5px;
  color: #fff;
}
.post-subline {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #94a3b8;
}
.audience-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}
.audience-tag.friends {
  background: rgba(236, 72, 153, 0.15);
  color: #ec4899;
}
.audience-tag.organization {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
}
.tag-ico {
  font-size: 13px;
}
.btn-icon-del,
.btn-icon-report {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
}
.btn-icon-del:hover {
  color: #ef4444;
}
.btn-icon-report:hover {
  color: #f59e0b;
}
.post-content {
  font-size: 14.5px;
  line-height: 1.55;
  color: #e2e8f0;
  white-space: pre-wrap;
  word-break: break-word;
}
.post-reactions-bar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}
.btn-reaction {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12.5px;
  color: #cbd5e1;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-reaction:hover {
  background: rgba(255, 255, 255, 0.1);
}
.count-badge {
  background: #3b82f6;
  color: #fff;
  border-radius: 10px;
  padding: 1px 6px;
  font-size: 11px;
  font-weight: 700;
}
.comments-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}
.comments-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.comment-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.comment-bubble {
  background: rgba(255, 255, 255, 0.04);
  padding: 6px 12px;
  border-radius: 10px;
  font-size: 13px;
  color: #cbd5e1;
}
.comment-author {
  color: #93c5fd;
  margin-right: 6px;
}
.btn-comment-del {
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
}
.btn-comment-del:hover {
  color: #ef4444;
}
.add-comment-row {
  display: flex;
  gap: 8px;
}
.comment-input {
  flex: 1;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 8px;
  padding: 6px 12px;
  color: #fff;
  font-size: 13px;
}
.btn-send-comment {
  background: #3b82f6;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 8px;
  cursor: pointer;
}
.btn-send-comment:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}
.modal-card {
  background: #1e293b;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 14px;
  padding: 24px;
  width: 100%;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.report-textarea {
  width: 100%;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 10px;
  color: #fff;
  font-size: 13.5px;
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.btn-cancel {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #cbd5e1;
  padding: 6px 14px;
  border-radius: 8px;
  cursor: pointer;
}
.btn-confirm-report {
  background: #ef4444;
  color: #fff;
  border: none;
  padding: 6px 14px;
  border-radius: 8px;
  cursor: pointer;
}
.icon-16 {
  font-size: 16px;
}
</style>
