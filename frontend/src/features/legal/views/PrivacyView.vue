<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'

const activeSection = ref('collection')

const sections = [
  { id: 'collection', title: '1. Thông tin thu thập', icon: 'badge' },
  { id: 'purpose', title: '2. Mục đích sử dụng dữ liệu', icon: 'target' },
  { id: 'payment-security', title: '3. Bảo mật thanh toán', icon: 'lock' },
  { id: 'third-party', title: '4. Chia sẻ với bên thứ ba', icon: 'share_reviews' },
  { id: 'user-rights', title: '5. Quyền của người dùng', icon: 'manage_accounts' },
  { id: 'cookies', title: '6. Cookie & Lưu trữ', icon: 'cookie' },
  { id: 'retention', title: '7. Thời gian lưu trữ dữ liệu', icon: 'history' },
  { id: 'dpo-contact', title: '8. Liên hệ bảo vệ dữ liệu', icon: 'contact_mail' },
]

function scrollToSection(id: string) {
  activeSection.value = id
  const el = document.getElementById(id)
  if (el) {
    const yOffset = -90
    const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset
    window.scrollTo({ top: y, behavior: 'smooth' })
  }
}

function handleScroll() {
  const scrollPos = window.scrollY + 120
  for (let i = sections.length - 1; i >= 0; i--) {
    const s = sections[i]
    if (!s) continue
    const el = document.getElementById(s.id)
    if (el && el.offsetTop <= scrollPos) {
      activeSection.value = s.id
      break
    }
  }
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true })
  if (window.location.hash) {
    const targetId = window.location.hash.replace('#', '')
    setTimeout(() => scrollToSection(targetId), 150)
  }
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
  <div class="legal-page-wrapper">
    <!-- Ambient light glow -->
    <div class="aura-glow-bg" aria-hidden="true"></div>

    <div class="container legal-container">
      <!-- Top Hero Header -->
      <div class="legal-header">
        <div class="legal-badge">
          <span class="material-symbols-outlined icon-18">shield</span>
          <span>{{ $t('legal.badge') }}</span>
        </div>
        <h1 class="legal-title">{{ $t('legal.privacyTitle') }}</h1>
        <p class="legal-subtitle">
          {{ $t('legal.privacySubtitle') }}
        </p>

        <!-- Navigation switcher -->
        <div class="doc-switcher">
          <RouterLink to="/terms" class="doc-tab">
            <span class="material-symbols-outlined">description</span>
            <span>{{ $t('legal.tabTerms') }}</span>
          </RouterLink>
          <span class="doc-tab active">
            <span class="material-symbols-outlined">shield</span>
            <span>{{ $t('legal.tabPrivacy') }}</span>
          </span>
        </div>

        <div class="legal-meta-row">
          <div class="meta-item">
            <span class="material-symbols-outlined">update</span>
            <span>{{ $t('legal.updatedDate') }}</span>
          </div>
          <div class="meta-item">
            <span class="material-symbols-outlined">lock_clock</span>
            <span>Mã hóa: <strong>SSL / TLS 256-bit &amp; PCI-DSS</strong></span>
          </div>
          <div class="meta-item">
            <span class="material-symbols-outlined">verified_user</span>
            <span>Tuân thủ: <strong>Nghị định 13/2023/NĐ-CP</strong></span>
          </div>
        </div>
      </div>

      <!-- Main Content Layout -->
      <div class="legal-grid">
        <!-- Sticky Sidebar Navigation -->
        <aside class="legal-sidebar">
          <div class="sidebar-sticky-card">
            <h3 class="sidebar-title">
              <span class="material-symbols-outlined">format_list_bulleted</span>
              <span>{{ $t('legal.tableOfContents') }}</span>
            </h3>
            <nav class="sidebar-nav">
              <button
                v-for="s in sections"
                :key="s.id"
                type="button"
                class="nav-link-btn"
                :class="{ active: activeSection === s.id }"
                @click="scrollToSection(s.id)"
              >
                <span class="material-symbols-outlined nav-icon">{{ s.icon }}</span>
                <span class="nav-text">{{ s.title }}</span>
              </button>
            </nav>

            <div class="sidebar-support-box">
              <div class="support-icon">
                <span class="material-symbols-outlined">verified_user</span>
              </div>
              <h4>{{ $t('legal.supportTitle') }}</h4>
              <p>{{ $t('legal.supportDesc') }}</p>
              <a href="mailto:ndviet.dev@gmail.com" class="support-btn">
                <span class="material-symbols-outlined">mail</span>
                <span>Gửi phản hồi DPO</span>
              </a>
            </div>
          </div>
        </aside>

        <!-- Main Document Body -->
        <main class="legal-body">
          <!-- Section 1 -->
          <section id="collection" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">badge</span>
              </div>
              <h2>1. Thông tin chúng tôi thu thập</h2>
            </div>
            <div class="section-text">
              <p>
                Khi bạn đăng ký tài khoản Aura ID và tương tác với hệ thống Aura Court, chúng tôi
                thu thập các thông tin tối thiểu cần thiết để phục vụ công tác điều phối sân và giao
                dịch:
              </p>
              <div class="data-types-grid">
                <div class="data-card">
                  <span class="material-symbols-outlined d-icon">person</span>
                  <h5>Thông tin tài khoản Aura ID</h5>
                  <p>
                    Họ tên, địa chỉ email, số điện thoại đăng ký và mật khẩu được mã hóa một chiều
                    chuẩn Bcrypt cao cấp.
                  </p>
                </div>
                <div class="data-card">
                  <span class="material-symbols-outlined d-icon">receipt_long</span>
                  <h5>Thông tin lịch thi đấu</h5>
                  <p>
                    Lịch sử các khung giờ đã đặt, tên sân (Tiêu chuẩn/VIP), ngày giờ thi đấu, trạng
                    thái thanh toán và mã QR check-in.
                  </p>
                </div>
                <div class="data-card">
                  <span class="material-symbols-outlined d-icon">devices</span>
                  <h5>Thông tin kỹ thuật &amp; Thiết bị</h5>
                  <p>
                    Địa chỉ IP, loại trình duyệt, nhật ký truy cập nhằm bảo vệ an ninh phòng chống
                    gian lận và tối ưu giao diện.
                  </p>
                </div>
              </div>
            </div>
          </section>

          <!-- Section 2 -->
          <section id="purpose" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">target</span>
              </div>
              <h2>2. Mục đích sử dụng dữ liệu</h2>
            </div>
            <div class="section-text">
              <p>Dữ liệu của hội viên chỉ được sử dụng cho các mục đích dịch vụ chính đáng:</p>
              <ul>
                <li>
                  <strong>Điều phối &amp; Khóa sân tức thì:</strong> Tự động khóa khung giờ, tạo mã
                  QR nhận sân và quản lý luồng check-in tại quầy lễ tân.
                </li>
                <li>
                  <strong>Xác nhận giao dịch thanh toán:</strong> Tự động đối soát chuyển khoản ngân
                  hàng qua cổng bảo mật SePay / VietQR trong vài giây.
                </li>
                <li>
                  <strong>Thông báo thông minh:</strong> Gửi thông báo nhắc lịch thi đấu, xác nhận
                  thanh toán hoặc cảnh báo thay đổi lịch thi đấu giải.
                </li>
                <li>
                  <strong>Nâng cao chất lượng dịch vụ:</strong> Thống kê tần suất sử dụng để điều
                  chỉnh ánh sáng, bảo dưỡng mặt thảm BWF và nâng cấp tiện ích.
                </li>
              </ul>
            </div>
          </section>

          <!-- Section 3 -->
          <section id="payment-security" class="content-card highlight-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">lock</span>
              </div>
              <h2>3. Bảo mật thanh toán &amp; Dữ liệu tài chính</h2>
            </div>
            <div class="section-text">
              <p>
                Aura Court áp dụng các tiêu chuẩn bảo mật tài chính ngân hàng nghiêm ngặt nhất nhằm
                bảo vệ an toàn 100% cho mọi giao dịch:
              </p>
              <div class="security-pillars">
                <div class="pillar-item">
                  <div class="pillar-check">
                    <span class="material-symbols-outlined">check_circle</span>
                  </div>
                  <div>
                    <strong>Không lưu trữ thông tin thẻ nhạy cảm:</strong>
                    <p>
                      Aura Court <em>không bao giờ</em> lưu trữ mật khẩu ngân hàng, mã OTP hoặc số
                      thẻ CVV/CVC của khách hàng trên máy chủ.
                    </p>
                  </div>
                </div>
                <div class="pillar-item">
                  <div class="pillar-check">
                    <span class="material-symbols-outlined">check_circle</span>
                  </div>
                  <div>
                    <strong>Đối soát tự động độc lập qua SePay / VietQR:</strong>
                    <p>
                      Giao dịch quét mã QR VietQR được xử lý qua hệ thống đối soát trung gian bảo
                      mật SePay đạt chuẩn an toàn tài chính quốc tế.
                    </p>
                  </div>
                </div>
                <div class="pillar-item">
                  <div class="pillar-check">
                    <span class="material-symbols-outlined">check_circle</span>
                  </div>
                  <div>
                    <strong>Mã hóa đường truyền TLS 1.3:</strong>
                    <p>
                      Toàn bộ dữ liệu truyền tải giữa thiết bị của bạn và hệ thống máy chủ Aura
                      Court được mã hóa đầu cuối với chứng chỉ SSL/TLS 256-bit cấp cao.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Section 4 -->
          <section id="third-party" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">share_reviews</span>
              </div>
              <h2>4. Chia sẻ dữ liệu với bên thứ ba</h2>
            </div>
            <div class="section-text">
              <p>
                Chúng tôi tuyệt đối
                <strong>không bán, cho thuê hoặc thương mại hóa dữ liệu cá nhân</strong> của bạn cho
                bất kỳ đối tác quảng cáo nào. Dữ liệu chỉ được chia sẻ trong phạm vi cần thiết:
              </p>
              <ul>
                <li>
                  <strong>Cổng thanh toán &amp; Ngân hàng liên kết:</strong> Cung cấp mã đơn hàng và
                  số tiền giao dịch để đối soát thành công đơn đặt sân.
                </li>
                <li>
                  <strong>Yêu cầu pháp lý bắt buộc:</strong> Chỉ cung cấp khi có văn bản yêu cầu
                  chính thức từ cơ quan quản lý nhà nước có thẩm quyền theo quy định của pháp luật
                  Việt Nam.
                </li>
              </ul>
            </div>
          </section>

          <!-- Section 5 -->
          <section id="user-rights" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">manage_accounts</span>
              </div>
              <h2>5. Quyền của người dùng với dữ liệu cá nhân</h2>
            </div>
            <div class="section-text">
              <p>
                Hội viên có toàn quyền kiểm soát thông tin cá nhân của mình tại Aura Court bất kỳ
                lúc nào:
              </p>
              <div class="rights-list">
                <div class="right-item">
                  <span class="material-symbols-outlined">visibility</span>
                  <div>
                    <strong>Quyền truy cập &amp; Xem lại:</strong>
                    <p>
                      Dễ dàng xem toàn bộ thông tin tài khoản, danh sách giao dịch và lịch sử đặt
                      sân trong trang Hồ sơ cá nhân.
                    </p>
                  </div>
                </div>
                <div class="right-item">
                  <span class="material-symbols-outlined">edit</span>
                  <div>
                    <strong>Quyền chỉnh sửa &amp; Cập nhật:</strong>
                    <p>
                      Cập nhật số điện thoại, đổi mật khẩu và tùy chọn nhận thông báo trong mục cài
                      đặt tài khoản.
                    </p>
                  </div>
                </div>
                <div class="right-item">
                  <span class="material-symbols-outlined">delete_forever</span>
                  <div>
                    <strong>Quyền yêu cầu xóa dữ liệu:</strong>
                    <p>
                      Gửi yêu cầu đóng tài khoản Aura ID vĩnh viễn và ẩn toàn bộ dữ liệu lịch sử
                      khỏi hệ thống công khai.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Section 6 -->
          <section id="cookies" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">cookie</span>
              </div>
              <h2>6. Cookie &amp; Bộ nhớ trình duyệt (Local Storage)</h2>
            </div>
            <div class="section-text">
              <p>
                Aura Court sử dụng Cookie kỹ thuật và Local Storage nhằm mang lại trải nghiệm duyệt
                web mượt mà:
              </p>
              <ul>
                <li>
                  <strong>Phiên đăng nhập an toàn (Session Cookie):</strong> Lưu giữ token xác thực
                  an toàn giữa các lượt truy cập mà không yêu cầu đăng nhập lại liên tục.
                </li>
                <li>
                  <strong>Tùy chỉnh giao diện:</strong> Ghi nhớ chế độ hiển thị Sáng / Tối (Light /
                  Dark Mode) và ngôn ngữ ưa thích (Tiếng Việt / English).
                </li>
                <li>
                  <strong>Phòng chống tấn công (CSRF Token):</strong> Bảo vệ form đặt sân và quy
                  trình thanh toán khỏi các tấn công giả mạo yêu cầu chéo trang.
                </li>
              </ul>
            </div>
          </section>

          <!-- Section 7 -->
          <section id="retention" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">history</span>
              </div>
              <h2>7. Thời gian lưu trữ dữ liệu</h2>
            </div>
            <div class="section-text">
              <p>
                Thông tin hồ sơ hội viên được lưu trữ trong suốt thời gian bạn duy trì tài khoản tại
                Aura Court. Nhật ký giao dịch và chứng từ thanh toán được lưu trữ bảo mật tối thiểu
                12 tháng phục vụ công tác đối soát kế toán trước khi được lưu trữ nén bảo mật.
              </p>
            </div>
          </section>

          <!-- Section 8 -->
          <section id="dpo-contact" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">contact_mail</span>
              </div>
              <h2>8. Liên hệ bộ phận bảo vệ dữ liệu (DPO)</h2>
            </div>
            <div class="section-text">
              <p>
                Nếu có bất kỳ thắc mắc nào liên quan đến chính sách quyền riêng tư hoặc muốn thực
                hiện quyền yêu cầu bảo mật thông tin, xin vui lòng liên hệ:
              </p>
              <div class="contact-card-box">
                <div class="c-item">
                  <span class="material-symbols-outlined">mail</span>
                  <div>
                    <strong>Email Phụ trách Bảo mật (DPO):</strong>
                    <a href="mailto:ndviet.dev@gmail.com">ndviet.dev@gmail.com</a>
                  </div>
                </div>
                <div class="c-item">
                  <span class="material-symbols-outlined">phone_in_talk</span>
                  <div>
                    <strong>Hotline hỗ trợ hội viên:</strong>
                    <a href="tel:0963792413">0963 792 413</a>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </main>
      </div>
    </div>
  </div>
</template>

<style scoped>
.legal-page-wrapper {
  position: relative;
  min-height: calc(100vh - 78px);
  padding: 40px 0 90px;
}

.aura-glow-bg {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 1000px;
  max-width: 90vw;
  height: 500px;
  background: radial-gradient(
    circle at center,
    rgba(201, 161, 90, 0.12) 0%,
    rgba(107, 45, 63, 0.08) 45%,
    transparent 70%
  );
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}

:global(html.dark .aura-glow-bg) {
  background: radial-gradient(
    circle at center,
    rgba(216, 180, 110, 0.15) 0%,
    rgba(68, 26, 38, 0.25) 50%,
    transparent 75%
  );
}

.legal-container {
  position: relative;
  z-index: 1;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

/* Header */
.legal-header {
  text-align: center;
  max-width: 780px;
  margin: 0 auto 48px;
}

.legal-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(201, 161, 90, 0.12);
  color: var(--on-accent-container, #684f17);
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 750;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  border: 1px solid rgba(201, 161, 90, 0.35);
  margin-bottom: 16px;
}

:global(html.dark .legal-badge) {
  background: rgba(223, 186, 114, 0.15);
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.35);
}

.icon-18 {
  font-size: 18px;
}

.legal-title {
  font-size: clamp(32px, 3.8vw, 46px);
  font-weight: 850;
  letter-spacing: -0.03em;
  color: var(--ink, #1c1618);
  margin: 0 0 14px;
}

:global(html.dark .legal-title) {
  color: #faf7f3;
}

.legal-subtitle {
  font-size: 16px;
  line-height: 1.6;
  color: var(--muted, #6e6668);
  margin: 0 0 24px;
}

:global(html.dark .legal-subtitle) {
  color: #a89fa4;
}

/* Switcher Tabs */
.doc-switcher {
  display: inline-flex;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  padding: 4px;
  border-radius: 14px;
  margin-bottom: 24px;
}

:global(html.dark .doc-switcher) {
  background: #262024;
  border-color: #382f35;
}

.doc-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--muted, #6e6668);
  text-decoration: none;
  border-radius: 10px;
  transition: all 0.2s ease;
}

:global(html.dark .doc-tab) {
  color: #a89fa4;
}

.doc-tab.active {
  background: var(--surface, #ffffff);
  color: var(--primary, #6b2d3f);
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.08);
}

:global(html.dark .doc-tab.active) {
  background: #30292e;
  color: #dfba72;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.doc-tab:hover:not(.active) {
  color: var(--ink, #1c1618);
}

:global(html.dark .doc-tab:hover:not(.active)) {
  color: #faf7f3;
}

.legal-meta-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 24px;
  flex-wrap: wrap;
  font-size: 13px;
  color: var(--muted, #6e6668);
}

:global(html.dark .legal-meta-row) {
  color: #a89fa4;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.meta-item .material-symbols-outlined {
  font-size: 18px;
  color: var(--accent, #c9a15a);
}

:global(html.dark .meta-item .material-symbols-outlined) {
  color: #dfba72;
}

/* Legal Grid Layout */
.legal-grid {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 40px;
  align-items: flex-start;
}

/* Sidebar */
.legal-sidebar {
  position: sticky;
  top: 100px;
}

.sidebar-sticky-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 20px;
  padding: 24px 20px;
  box-shadow: var(--shadow-sm, 0 4px 16px rgba(107, 45, 63, 0.06));
}

:global(html.dark .sidebar-sticky-card) {
  background: var(--surface, #1e191c);
  border-color: var(--border, #382f35);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.sidebar-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--ink, #1c1618);
  margin: 0 0 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border, #e8e1d7);
}

:global(html.dark .sidebar-title) {
  color: #faf7f3;
  border-color: #382f35;
}

.sidebar-title .material-symbols-outlined {
  font-size: 18px;
  color: var(--primary, #6b2d3f);
}

:global(html.dark .sidebar-title .material-symbols-outlined) {
  color: #dfba72;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 24px;
}

.nav-link-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  font-size: 13px;
  font-weight: 500;
  color: var(--muted, #6e6668);
  background: transparent;
  border: none;
  border-radius: 10px;
  text-align: left;
  cursor: pointer;
  transition: all 0.18s ease;
}

:global(html.dark .nav-link-btn) {
  color: #a89fa4;
}

.nav-link-btn:hover {
  background: var(--surface-subtle, #f7f3ed);
  color: var(--ink, #1c1618);
}

:global(html.dark .nav-link-btn:hover) {
  background: #262024;
  color: #faf7f3;
}

.nav-link-btn.active {
  background: var(--accent-container, #faf2e3);
  color: var(--on-accent-container, #684f17);
  font-weight: 700;
}

:global(html.dark .nav-link-btn.active) {
  background: rgba(223, 186, 114, 0.18);
  color: #dfba72;
}

.nav-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.nav-text {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Sidebar Support Box */
.sidebar-support-box {
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 14px;
  padding: 18px 16px;
  text-align: center;
}

:global(html.dark .sidebar-support-box) {
  background: #262024;
  border-color: #382f35;
}

.support-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--surface, #ffffff);
  color: var(--accent, #c9a15a);
  border: 1px solid var(--border, #e8e1d7);
  display: grid;
  place-items: center;
  margin: 0 auto 10px;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.08);
}

:global(html.dark .support-icon) {
  background: #30292e;
  border-color: #382f35;
  color: #dfba72;
}

.sidebar-support-box h4 {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink, #1c1618);
  margin: 0 0 4px;
}

:global(html.dark .sidebar-support-box h4) {
  color: #faf7f3;
}

.sidebar-support-box p {
  font-size: 12px;
  color: var(--muted, #6e6668);
  margin: 0 0 12px;
  line-height: 1.4;
}

:global(html.dark .sidebar-support-box p) {
  color: #a89fa4;
}

.support-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 8px 14px;
  background: var(--accent, #c9a15a);
  color: #1c1618;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 750;
  text-decoration: none;
  transition: all 0.2s ease;
}

.support-btn:hover {
  filter: brightness(1.1);
  transform: translateY(-1px);
}

:global(html.dark .support-btn) {
  background: #dfba72;
  color: #1c1618;
}

/* Content Body */
.legal-body {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.content-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 24px;
  padding: 36px;
  box-shadow: var(--shadow-sm, 0 4px 16px rgba(107, 45, 63, 0.06));
  scroll-margin-top: 100px;
}

:global(html.dark .content-card) {
  background: var(--surface, #1e191c);
  border-color: var(--border, #382f35);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.section-head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border, #e8e1d7);
}

:global(html.dark .section-head) {
  border-color: #382f35;
}

.section-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(201, 161, 90, 0.12);
  color: var(--on-accent-container, #684f17);
  border: 1px solid rgba(201, 161, 90, 0.3);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

:global(html.dark .section-icon-box) {
  background: rgba(223, 186, 114, 0.15);
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.35);
}

.section-icon-box .material-symbols-outlined {
  font-size: 22px;
}

.section-head h2 {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink, #1c1618);
  margin: 0;
  letter-spacing: -0.02em;
}

:global(html.dark .section-head h2) {
  color: #faf7f3;
}

.section-text {
  font-size: 14.5px;
  line-height: 1.7;
  color: var(--ink, #1c1618);
}

:global(html.dark .section-text) {
  color: #e5dee3;
}

.section-text p {
  margin: 0 0 16px;
  color: var(--ink, #1c1618);
}

:global(html.dark .section-text p) {
  color: #e5dee3;
}

.section-text ul {
  padding-left: 20px;
  margin: 0 0 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-text li {
  color: var(--ink, #1c1618);
}

:global(html.dark .section-text li) {
  color: #e5dee3;
}

/* Data types grid */
.data-types-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-top: 18px;
}

.data-card {
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 14px;
  padding: 18px 16px;
  transition:
    transform 0.2s ease,
    border-color 0.2s ease;
}

.data-card:hover {
  transform: translateY(-2px);
  border-color: rgba(201, 161, 90, 0.4);
}

:global(html.dark .data-card) {
  background: #262024;
  border-color: #382f35;
}

:global(html.dark .data-card:hover) {
  border-color: rgba(223, 186, 114, 0.4);
}

.d-icon {
  font-size: 26px;
  color: var(--accent, #c9a15a);
  margin-bottom: 8px;
  display: block;
}

:global(html.dark .d-icon) {
  color: #dfba72;
}

.data-card h5 {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #1c1618);
  margin: 0 0 6px;
}

:global(html.dark .data-card h5) {
  color: #faf7f3;
}

.data-card p {
  font-size: 12.5px;
  color: var(--muted, #6e6668);
  line-height: 1.5;
  margin: 0;
}

:global(html.dark .data-card p) {
  color: #a89fa4;
}

/* Security Pillars */
.security-pillars {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 16px;
}

.pillar-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 14px;
  padding: 16px 18px;
}

:global(html.dark .pillar-item) {
  background: #262024;
  border-color: #382f35;
}

.pillar-check .material-symbols-outlined {
  font-size: 22px;
  color: #0e6b50;
  margin-top: 2px;
}

:global(html.dark .pillar-check .material-symbols-outlined) {
  color: #34d399;
}

.pillar-item strong {
  font-size: 14px;
  color: var(--ink, #1c1618);
  display: block;
  margin-bottom: 2px;
}

:global(html.dark .pillar-item strong) {
  color: #faf7f3;
}

.pillar-item p {
  font-size: 13px;
  color: var(--muted, #6e6668);
  margin: 0;
  line-height: 1.5;
}

:global(html.dark .pillar-item p) {
  color: #a89fa4;
}

/* Rights List */
.rights-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 18px;
}

.right-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 14px;
  padding: 16px 18px;
}

:global(html.dark .right-item) {
  background: #262024;
  border-color: #382f35;
}

.right-item .material-symbols-outlined {
  font-size: 22px;
  color: var(--accent, #c9a15a);
  flex-shrink: 0;
  margin-top: 2px;
}

:global(html.dark .right-item .material-symbols-outlined) {
  color: #dfba72;
}

.right-item strong {
  font-size: 14px;
  color: var(--ink, #1c1618);
  display: block;
  margin-bottom: 2px;
}

:global(html.dark .right-item strong) {
  color: #faf7f3;
}

.right-item p {
  font-size: 13px;
  color: var(--muted, #6e6668);
  margin: 0;
  line-height: 1.5;
}

:global(html.dark .right-item p) {
  color: #a89fa4;
}

/* Contact Cards */
.contact-card-box {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 16px;
  margin-top: 20px;
}

.c-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 14px;
  padding: 16px 18px;
}

:global(html.dark .c-item) {
  background: #262024;
  border-color: #382f35;
}

.c-item .material-symbols-outlined {
  font-size: 22px;
  color: var(--accent, #c9a15a);
  flex-shrink: 0;
  margin-top: 2px;
}

:global(html.dark .c-item .material-symbols-outlined) {
  color: #dfba72;
}

.c-item strong {
  display: block;
  font-size: 13px;
  color: var(--ink, #1c1618);
  margin-bottom: 2px;
}

:global(html.dark .c-item strong) {
  color: #faf7f3;
}

.c-item a,
.c-item span {
  font-size: 13.5px;
  color: var(--accent, #c9a15a);
  text-decoration: none;
  font-weight: 600;
}

:global(html.dark .c-item a),
:global(html.dark .c-item span) {
  color: #dfba72;
}

.c-item a:hover {
  text-decoration: underline;
}

/* Responsive */
@media (max-width: 960px) {
  .legal-grid {
    grid-template-columns: 1fr;
    gap: 28px;
  }
  .legal-sidebar {
    position: static;
  }
  .content-card {
    padding: 24px 20px;
  }
}
</style>
