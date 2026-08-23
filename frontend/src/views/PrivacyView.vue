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
    <div class="aura-glow-bg" aria-hidden="true"></div>

    <div class="container legal-container">
      
      <!-- Top Hero Header -->
      <div class="legal-header">
        <div class="legal-badge">
          <span class="material-symbols-outlined">security</span>
          <span>BẢO MẬT & QUYỀN RIÊNG TƯ AURA COURT</span>
        </div>
        <h1 class="legal-title">Chính sách bảo mật</h1>
        <p class="legal-subtitle">
          Aura Court cam kết tôn trọng, bảo vệ thông tin cá nhân và dữ liệu thanh toán của bạn theo tiêu chuẩn an toàn cao nhất.
        </p>

        <!-- Navigation switcher -->
        <div class="doc-switcher">
          <RouterLink to="/terms" class="doc-tab">
            <span class="material-symbols-outlined">description</span>
            <span>Điều khoản dịch vụ</span>
          </RouterLink>
          <span class="doc-tab active">
            <span class="material-symbols-outlined">shield</span>
            <span>Chính sách bảo mật</span>
          </span>
        </div>

        <div class="legal-meta-row">
          <div class="meta-item">
            <span class="material-symbols-outlined">update</span>
            <span>Cập nhật lần cuối: <strong>Tháng 08/2026</strong></span>
          </div>
          <div class="meta-item">
            <span class="material-symbols-outlined">lock_clock</span>
            <span>Tiêu chuẩn mã hóa: <strong>SSL / TLS 256-bit</strong></span>
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
              <span>Mục lục bảo mật</span>
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
              <h4>Cam kết bảo mật 100%</h4>
              <p>Không bao giờ mua bán hoặc trao đổi thông tin khách hàng.</p>
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
                Khi bạn tương tác với hệ thống Aura Court, chúng tôi thu thập các thông tin cần thiết nhằm cung cấp dịch vụ đặt sân tốt nhất:
              </p>
              <div class="data-types-grid">
                <div class="data-card">
                  <span class="material-symbols-outlined d-icon">person</span>
                  <h5>Thông tin tài khoản</h5>
                  <p>Họ tên, địa chỉ email, số điện thoại đăng ký và mật khẩu đã được mã hóa (Bcrypt Hash).</p>
                </div>
                <div class="data-card">
                  <span class="material-symbols-outlined d-icon">receipt_long</span>
                  <h5>Thông tin đặt sân</h5>
                  <p>Lịch sử các khung giờ đã đặt, tên sân, ngày thi đấu, trạng thái thanh toán và mã hóa đơn.</p>
                </div>
                <div class="data-card">
                  <span class="material-symbols-outlined d-icon">devices</span>
                  <h5>Thông tin kỹ thuật</h5>
                  <p>Địa chỉ IP, loại trình duyệt, hệ điều hành và thiết bị truy cập để tối ưu trải nghiệm hiển thị.</p>
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
              <p>Dữ liệu của quý khách chỉ được sử dụng cho các mục đích chính đáng sau:</p>
              <ul>
                <li><strong>Xử lý đơn đặt sân:</strong> Khóa khung giờ, tạo mã QR nhận sân và kích hoạt quyền vào sân thi đấu.</li>
                <li><strong>Xác nhận giao dịch:</strong> Đối soát chuyển khoản ngân hàng tự động qua SePay/VietQR.</li>
                <li><strong>Thông báo kịp thời:</strong> Gửi thông báo nhắc lịch thi đấu, cảnh báo bảo trì khẩn cấp hoặc xác nhận hoàn tiền.</li>
                <li><strong>Nâng cao trải nghiệm:</strong> Phân tích giờ cao điểm để điều chỉnh biểu giá hợp lý và nâng cấp cơ sở vật chất.</li>
              </ul>
            </div>
          </section>

          <!-- Section 3 -->
          <section id="payment-security" class="content-card highlight-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">lock</span>
              </div>
              <h2>3. Bảo mật thanh toán & Dữ liệu tài chính</h2>
            </div>
            <div class="section-text">
              <p>
                Aura Court áp dụng các tiêu chuẩn bảo mật tài chính nghiêm ngặt nhằm đảm bảo an toàn tuyệt đối:
              </p>
              <div class="security-pillars">
                <div class="pillar-item">
                  <div class="pillar-check"><span class="material-symbols-outlined">check_circle</span></div>
                  <div>
                    <strong>Không lưu thông tin nhạy cảm:</strong>
                    <p>Aura Court <em>không bao giờ</em> lưu trữ mật khẩu ngân hàng, mã OTP hoặc số thẻ CVV của khách hàng.</p>
                  </div>
                </div>
                <div class="pillar-item">
                  <div class="pillar-check"><span class="material-symbols-outlined">check_circle</span></div>
                  <div>
                    <strong>Đối soát tự động độc lập:</strong>
                    <p>Giao dịch quét mã QR VietQR được xử lý qua hệ thống đối soát trung gian bảo mật SePay có chứng nhận an toàn tài chính.</p>
                  </div>
                </div>
                <div class="pillar-item">
                  <div class="pillar-check"><span class="material-symbols-outlined">check_circle</span></div>
                  <div>
                    <strong>Mã hóa toàn diện:</strong>
                    <p>Toàn bộ đường truyền dữ liệu giữa trình duyệt của bạn và máy chủ Aura Court được bảo vệ bởi chứng chỉ mã hóa TLS 1.3 cao cấp nhất.</p>
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
                Chúng tôi tuyệt đối <strong>không bán, cho thuê hoặc thương mại hóa dữ liệu cá nhân</strong> của bạn cho bất kỳ bên thứ ba nào. Dữ liệu chỉ được chia sẻ trong các trường hợp sau:
              </p>
              <ul>
                <li><strong>Cổng thanh toán ngân hàng:</strong> Chia sẻ mã đơn hàng và số tiền để ngân hàng xác nhận trạng thái chuyển khoản.</li>
                <li><strong>Tuân thủ pháp luật:</strong> Cung cấp thông tin khi có yêu cầu bằng văn bản chính thức từ cơ quan quản lý nhà nước có thẩm quyền theo quy định pháp luật Việt Nam.</li>
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
              <p>Bạn luôn có toàn quyền kiểm soát thông tin cá nhân của mình tại Aura Court:</p>
              <div class="rights-list">
                <div class="right-item">
                  <span class="material-symbols-outlined">visibility</span>
                  <div>
                    <strong>Quyền xem & truy cập:</strong>
                    <p>Xem toàn bộ thông tin tài khoản và lịch sử đặt sân trong trang Hồ sơ cá nhân.</p>
                  </div>
                </div>
                <div class="right-item">
                  <span class="material-symbols-outlined">edit</span>
                  <div>
                    <strong>Quyền chỉnh sửa:</strong>
                    <p>Cập nhật tên hiển thị, mật khẩu và cài đặt ngôn ngữ bất kỳ lúc nào.</p>
                  </div>
                </div>
                <div class="right-item">
                  <span class="material-symbols-outlined">delete_forever</span>
                  <div>
                    <strong>Quyền yêu cầu xóa dữ liệu:</strong>
                    <p>Gửi yêu cầu xóa tài khoản vĩnh viễn và ẩn thông tin giao dịch khỏi hệ thống.</p>
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
              <h2>6. Cookie & Bộ nhớ trình duyệt (Local Storage)</h2>
            </div>
            <div class="section-text">
              <p>
                Aura Court sử dụng Cookie kỹ thuật và Local Storage nhằm duy trì phiên làm việc mượt mà:
              </p>
              <ul>
                <li><strong>Phiên đăng nhập (Session Cookie):</strong> Giữ trạng thái đăng nhập bảo mật giữa các lần mở trang.</li>
                <li><strong>Cài đặt giao diện:</strong> Ghi nhớ tùy chọn Chế độ sáng / tối (Theme) và ngôn ngữ hiển thị (Tiếng Việt / English).</li>
                <li><strong>Bảo mật chống tấn công (CSRF Token):</strong> Bảo vệ form thanh toán và form đăng ký khỏi các cuộc tấn công giả mạo yêu cầu.</li>
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
                Thông tin tài khoản được lưu trữ trong suốt thời gian bạn duy trì tài khoản tại Aura Court. Lịch sử đơn đặt sân và hóa đơn thanh toán được lưu trữ tối thiểu 12 tháng theo quy định kế toán đối soát trước khi được lưu trữ nén bảo mật.
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
                Nếu có bất kỳ câu hỏi nào về chính sách bảo mật hoặc muốn thực hiện quyền xóa dữ liệu cá nhân, vui lòng liên hệ với phụ trách bảo mật của chúng tôi:
              </p>
              <div class="contact-card-box">
                <div class="c-item">
                  <span class="material-symbols-outlined">mail</span>
                  <div>
                    <strong>Email Bộ phận Bảo mật (DPO):</strong>
                    <a href="mailto:ndviet.dev@gmail.com">ndviet.dev@gmail.com</a>
                  </div>
                </div>
                <div class="c-item">
                  <span class="material-symbols-outlined">phone_in_talk</span>
                  <div>
                    <strong>Đường dây nóng hỗ trợ:</strong>
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
  top: 60px;
  left: 50%;
  transform: translateX(-50%);
  width: 900px;
  max-width: 90vw;
  height: 450px;
  background: radial-gradient(circle, rgba(255, 209, 220, 0.4) 0%, rgba(120, 85, 94, 0.06) 50%, transparent 70%);
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}

html.dark .aura-glow-bg {
  background: radial-gradient(circle, rgba(120, 85, 94, 0.35) 0%, rgba(255, 209, 220, 0.04) 50%, transparent 70%);
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
  background: rgba(120, 85, 94, 0.12);
  color: var(--pink-dark);
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.12em;
  margin-bottom: 16px;
}

html.dark .legal-badge {
  background: rgba(255, 209, 220, 0.15);
  color: #f8b4c4;
}

.legal-title {
  font-size: clamp(32px, 3.8vw, 46px);
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ink);
  margin: 0 0 14px;
}

.legal-subtitle {
  font-size: 16px;
  line-height: 1.6;
  color: var(--muted);
  margin: 0 0 24px;
}

/* Switcher Tabs */
.doc-switcher {
  display: inline-flex;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  padding: 4px;
  border-radius: 14px;
  margin-bottom: 24px;
}

.doc-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--muted);
  text-decoration: none;
  border-radius: 10px;
  transition: all 0.2s ease;
}

.doc-tab.active {
  background: var(--surface);
  color: var(--pink-dark);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

html.dark .doc-tab.active {
  background: var(--soft);
  color: #f8b4c4;
}

.doc-tab:hover:not(.active) {
  color: var(--ink);
}

.legal-meta-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 24px;
  flex-wrap: wrap;
  font-size: 13px;
  color: var(--muted);
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.meta-item .material-symbols-outlined {
  font-size: 18px;
  color: var(--pink-dark);
}

html.dark .meta-item .material-symbols-outlined {
  color: #f8b4c4;
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
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 24px 20px;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
}

.sidebar-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--ink);
  margin: 0 0 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border);
}

.sidebar-title .material-symbols-outlined {
  font-size: 18px;
  color: var(--pink-dark);
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
  color: var(--muted);
  background: transparent;
  border: none;
  border-radius: 10px;
  text-align: left;
  cursor: pointer;
  transition: all 0.18s ease;
}

.nav-link-btn:hover {
  background: var(--surface-subtle);
  color: var(--ink);
}

.nav-link-btn.active {
  background: var(--pink-light);
  color: var(--pink-dark);
  font-weight: 700;
}

html.dark .nav-link-btn.active {
  background: rgba(120, 85, 94, 0.4);
  color: #f8b4c4;
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
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 18px 16px;
  text-align: center;
}

.support-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--surface);
  color: var(--pink-dark);
  display: grid;
  place-items: center;
  margin: 0 auto 10px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

html.dark .support-icon {
  color: #f8b4c4;
}

.sidebar-support-box h4 {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px;
}

.sidebar-support-box p {
  font-size: 12px;
  color: var(--muted);
  margin: 0 0 12px;
  line-height: 1.4;
}

.support-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 8px 14px;
  background: var(--pink-dark);
  color: #ffffff;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}

.support-btn:hover {
  filter: brightness(1.1);
  transform: translateY(-1px);
}

/* Content Body */
.legal-body {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.content-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 36px 36px;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.04);
  scroll-margin-top: 100px;
}

.section-head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border);
}

.section-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--pink-light);
  color: var(--pink-dark);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

html.dark .section-icon-box {
  background: rgba(120, 85, 94, 0.35);
  color: #f8b4c4;
}

.section-icon-box .material-symbols-outlined {
  font-size: 22px;
}

.section-head h2 {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink);
  margin: 0;
  letter-spacing: -0.02em;
}

.section-text {
  font-size: 14.5px;
  line-height: 1.7;
  color: var(--ink);
}

.section-text p {
  margin: 0 0 16px;
  color: var(--ink);
}

.section-text ul {
  padding-left: 20px;
  margin: 0 0 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-text li {
  color: var(--ink);
}

/* Data types grid */
.data-types-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-top: 18px;
}

.data-card {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 18px 16px;
}

.d-icon {
  font-size: 26px;
  color: var(--pink-dark);
  margin-bottom: 8px;
}

html.dark .d-icon {
  color: #f8b4c4;
}

.data-card h5 {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 6px;
}

.data-card p {
  font-size: 12.5px;
  color: var(--muted);
  line-height: 1.5;
  margin: 0;
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
  background: var(--surface-subtle);
  border-radius: 14px;
  padding: 16px 18px;
}

.pillar-check .material-symbols-outlined {
  font-size: 22px;
  color: #0e6b50;
  margin-top: 2px;
}

html.dark .pillar-check .material-symbols-outlined {
  color: #34d399;
}

.pillar-item strong {
  font-size: 14px;
  color: var(--ink);
  display: block;
  margin-bottom: 2px;
}

.pillar-item p {
  font-size: 13px;
  color: var(--muted);
  margin: 0;
  line-height: 1.5;
}

/* Rights List */
.rights-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 16px;
}

.right-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: var(--surface-subtle);
  border-radius: 14px;
  padding: 14px 18px;
}

.right-item .material-symbols-outlined {
  font-size: 22px;
  color: var(--pink-dark);
  flex-shrink: 0;
  margin-top: 2px;
}

html.dark .right-item .material-symbols-outlined {
  color: #f8b4c4;
}

.right-item strong {
  font-size: 14px;
  color: var(--ink);
  display: block;
  margin-bottom: 2px;
}

.right-item p {
  font-size: 13px;
  color: var(--muted);
  margin: 0;
  line-height: 1.4;
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
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px 18px;
}

.c-item .material-symbols-outlined {
  font-size: 22px;
  color: var(--pink-dark);
  flex-shrink: 0;
  margin-top: 2px;
}

html.dark .c-item .material-symbols-outlined {
  color: #f8b4c4;
}

.c-item strong {
  display: block;
  font-size: 13px;
  color: var(--ink);
  margin-bottom: 2px;
}

.c-item a,
.c-item span {
  font-size: 13.5px;
  color: var(--pink-dark);
  text-decoration: none;
}

html.dark .c-item a,
html.dark .c-item span {
  color: #f8b4c4;
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
