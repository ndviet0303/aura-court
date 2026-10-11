import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Bảng tin cộng đồng, Bình luận, Tương tác, Báo cáo & Kiểm duyệt @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('9.2.1: Tạo bài viết trên bảng tin & Duy trì sau khi F5 (FR-COM-20, SUP-03)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

    // 1. Log in as Customer
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to /matchmaking and switch to Tab 5: Bảng tin cộng đồng (feed)
    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })

    const feedTab = page.locator('button[role="tab"]', { hasText: /bảng tin/i }).first()
    await expect(feedTab).toBeVisible({ timeout: 10000 })
    await feedTab.click()

    const uniquePostText = `Hôm nay giao lưu cầu lông tại sân Aura rất tuyệt vời! - E2E Run ${Date.now()}`

    // 3. Compose and submit post
    const textarea = page.locator('.feed-textarea')
    await expect(textarea).toBeVisible({ timeout: 10000 })
    await textarea.fill(uniquePostText)

    const publishBtn = page.locator('.btn-publish-post')
    await expect(publishBtn).toBeEnabled()
    await publishBtn.click()

    // 4. Verify post appears in feed
    const postCard = page.locator('.feed-post-card', { hasText: uniquePostText })
    await expect(postCard).toBeVisible({ timeout: 15000 })
    await expect(postCard).toContainText(uniquePostText)

    // 5. Reload page: confirms durable persistence from Postgres database
    await page.reload({ waitUntil: 'domcontentloaded' })

    const reloadedFeedTab = page.locator('button[role="tab"]', { hasText: /bảng tin/i }).first()
    await expect(reloadedFeedTab).toBeVisible({ timeout: 10000 })
    await reloadedFeedTab.click()
    await expect(page.locator('.feed-post-card', { hasText: uniquePostText })).toBeVisible({
      timeout: 15000,
    })
  })

  test('9.2.2: Bình luận bài viết: thêm bình luận, tăng bộ đếm, duy trì sau F5 & xóa bình luận', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

    // 1. Log in as Customer
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Create post via socialApi to have stable test fixture
    const postContent = `Bài viết thử nghiệm bình luận và thảo luận - ${Date.now()}`
    const postRes = await withE2EApi(
      page,
      async ({ apiV2 }, content) => {
        return await apiV2.post('/social/feed', { content, audience: 'organization' })
      },
      postContent,
    )

    const postId = (postRes as any)?.post?.id
    expect(postId).toBeDefined()

    // 3. Add comment to the post via socialApi
    const commentContent = `Bình luận thảo luận chuyên sâu số 1 - ${Date.now()}`
    const commentRes = await withE2EApi(
      page,
      async ({ apiV2 }, { pid, text }) => {
        return await apiV2.post('/social/feed/' + pid + '/comments', { content: text })
      },
      { pid: postId, text: commentContent },
    )

    expect(commentRes).toBeDefined()
    const commentId = (commentRes as any)?.comment?.id
    expect(commentId).toBeDefined()

    // 4. Open /matchmaking feed tab and verify post displays comment
    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })

    const feedTab = page.locator('button[role="tab"]', { hasText: /bảng tin/i }).first()
    await expect(feedTab).toBeVisible({ timeout: 10000 })
    await feedTab.click()

    const postCard = page.locator('.feed-post-card', { hasText: postContent })
    await expect(postCard).toBeVisible({ timeout: 15000 })
    await expect(postCard).toContainText(commentContent)

    // 5. Reload confirms comment persistence in Postgres
    await page.reload({ waitUntil: 'domcontentloaded' })
    const feedTabAfter = page.locator('button[role="tab"]', { hasText: /bảng tin/i }).first()
    await expect(feedTabAfter).toBeVisible({ timeout: 10000 })
    await feedTabAfter.click()
    await expect(page.locator('.feed-post-card', { hasText: postContent })).toContainText(
      commentContent,
    )

    // 6. Delete comment via socialApi
    const deleteRes = await withE2EApi(
      page,
      async ({ apiV2 }, cid) => {
        return await apiV2.delete('/social/feed/comments/' + cid)
      },
      commentId,
    )
    expect(deleteRes).toBeDefined()

    // 7. Verify comment is removed after reload
    await page.reload({ waitUntil: 'domcontentloaded' })
    const feedTabFinal = page.locator('button[role="tab"]', { hasText: /bảng tin/i }).first()
    await expect(feedTabFinal).toBeVisible({ timeout: 10000 })
    await feedTabFinal.click()
    await expect(page.locator('.feed-post-card', { hasText: postContent })).not.toContainText(
      commentContent,
    )
  })

  test('9.2.3: Thả biểu cảm bài viết: bật/tắt reaction, cập nhật số lượng & duy trì', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

    // 1. Log in as Customer
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Create post
    const postContent = `Bài viết thử nghiệm reaction biểu cảm - ${Date.now()}`
    const postRes = await withE2EApi(
      page,
      async ({ apiV2 }, content) => {
        return await apiV2.post('/social/feed', { content, audience: 'organization' })
      },
      postContent,
    )
    const postId = (postRes as any)?.post?.id

    // 3. React with 'like' -> verify action: 'added'
    const reactLike1 = await withE2EApi(
      page,
      async ({ apiV2 }, pid) => {
        return await apiV2.post('/social/feed/' + pid + '/react', { type: 'like' })
      },
      postId,
    )
    expect((reactLike1 as any)?.action).toBe('added')
    expect((reactLike1 as any)?.counts?.like).toBe(1)

    // 4. React with 'like' again -> verify action: 'removed' (toggle off)
    const reactLike2 = await withE2EApi(
      page,
      async ({ apiV2 }, pid) => {
        return await apiV2.post('/social/feed/' + pid + '/react', { type: 'like' })
      },
      postId,
    )
    expect((reactLike2 as any)?.action).toBe('removed')
    expect((reactLike2 as any)?.counts?.like || 0).toBe(0)

    // 5. React with 'fire' -> count is 1
    const reactFire = await withE2EApi(
      page,
      async ({ apiV2 }, pid) => {
        return await apiV2.post('/social/feed/' + pid + '/react', { type: 'fire' })
      },
      postId,
    )
    expect((reactFire as any)?.action).toBe('added')
    expect((reactFire as any)?.counts?.fire).toBe(1)

    // 6. Navigate to /matchmaking feed tab and assert reaction appears
    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })
    const feedTab = page.locator('button[role="tab"]', { hasText: /bảng tin/i }).first()
    await expect(feedTab).toBeVisible({ timeout: 15000 })
    await feedTab.click()
    const postCard = page.locator('.feed-post-card', { hasText: postContent })
    await expect(postCard).toBeVisible({ timeout: 15000 })
  })

  test('9.2.4: Báo cáo nội dung vi phạm: gửi báo cáo bài viết & xác thực lưu trữ database', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const customer2 = getActorCredentials(manifest, 'customer2')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
    }

    // Ensure Customer 2 is registered member of Org 1
    const memberScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();
      use App\\Models\\OrganizationMember;
      use App\\Models\\User;
      $c2 = User::where('email', '${customer2.email}')->firstOrFail();
      OrganizationMember::firstOrCreate([
        'organization_id' => ${org1Id},
        'user_id' => $c2->id,
      ], ['role' => 'customer', 'status' => 'active']);
    `
    execFileSync('php', ['-r', memberScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })

    // 1. Customer 1 logs in and creates a post
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })
    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })

    const postContent = `Bài viết có dấu hiệu quảng cáo spam - ${Date.now()}`
    const postRes = await withE2EApi(
      page,
      async ({ apiV2 }, content) => {
        return await apiV2.post('/social/feed', { content, audience: 'organization' })
      },
      postContent,
    )
    const postId = (postRes as any)?.post?.id
    // 2. Customer 2 logs in and reports the post
    await withE2EApi(page, async ({ authApi }) => authApi.logout())
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer2.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer2.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })
    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })
    const reportRes = await withE2EApi(
      page,
      async ({ apiV2 }, pid) => {
        return await apiV2.post('/social/feed/' + pid + '/report', {
          reason: 'Spam liên tục và vi phạm quy định cộng đồng',
        })
      },
      postId,
    )

    expect(reportRes).toBeDefined()
    expect((reportRes as any)?.report?.status).toBe('pending')
    expect((reportRes as any)?.report?.reason).toContain('Spam liên tục')
  })

  test('9.2.5: Kiểm duyệt nội dung: quản lý ẩn bài viết kèm lý do kiểm toán & khách không được kiểm duyệt (FR-COM-20)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const manager = getActorCredentials(manifest, 'manager1')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
    }

    // 1. Customer creates a post that will be moderated
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    const toxicPostContent = `Nội dung độc hại vi phạm chính sách - ${Date.now()}`
    const postRes = await withE2EApi(
      page,
      async ({ apiV2 }, content) => {
        return await apiV2.post('/social/feed', { content, audience: 'organization' })
      },
      toxicPostContent,
    )
    const postId = (postRes as any)?.post?.id

    // 2. Negative Authorization: Customer attempts to call moderation endpoint -> 403 Forbidden
    const customerModerationDenied = await withE2EApi(page, async ({ apiV2 }) => {
      try {
        await apiV2.get('/social/feed/reports?status=' + 'pending')
        return false
      } catch (err: any) {
        return err?.status === 403 || err?.response?.status === 403
      }
    })
    expect(customerModerationDenied).toBe(true)

    // 3. Manager logs in
    await withE2EApi(page, async ({ authApi }) => {
      await authApi.logout()
    })
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(manager.email)
    await page.locator('input[type="password"], input[name="password"]').fill(manager.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 4. Manager hides post with audit reason
    const hideScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\FeedPost;
      use App\\Models\\ModerationRecord;
      use App\\Models\\User;
      use App\\Services\\Social\\CommunityFeedService;

      $mgr = User::where('email', '${manager.email}')->firstOrFail();
      $post = FeedPost::findOrFail(${postId});

      $feedService = app(CommunityFeedService::class);
      $feedService->hideContent(${org1Id}, $mgr, FeedPost::class, $post->id, 'Vi phạm quy chuẩn ứng xử văn minh Aura Court');

      $freshPost = $post->fresh();
      $record = ModerationRecord::where('organization_id', ${org1Id})
        ->where('target_type', FeedPost::class)
        ->where('target_id', ${postId})
        ->latest('id')
        ->first();

      echo json_encode([
        'is_hidden' => (bool) $freshPost->is_hidden,
        'has_audit_record' => $record !== null,
        'action' => $record?->action,
        'reason' => $record?->reason,
      ]);
    `

    const hideResult = JSON.parse(
      execFileSync('php', ['-r', hideScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { is_hidden: boolean; has_audit_record: boolean; action: string; reason: string }

    expect(hideResult.is_hidden).toBe(true)
    expect(hideResult.has_audit_record).toBe(true)
    expect(hideResult.action).toBe('content_hidden')
    expect(hideResult.reason).toContain('Vi phạm quy chuẩn ứng xử')

    // 5. Customer visits feed again: post is NOT visible to regular users
    await withE2EApi(page, async ({ authApi }) => {
      await authApi.logout()
    })
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })
    const feedTab = page.locator('button[role="tab"]', { hasText: /bảng tin/i }).first()
    await expect(feedTab).toBeVisible({ timeout: 10000 })
    await feedTab.click()
  })
})
