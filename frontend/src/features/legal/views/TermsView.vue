<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'

const activeSection = ref('general')

const sections = [
  { id: 'general', title: '1. Điều khoản chung & Định nghĩa', icon: 'gavel' },
  { id: 'account', title: '2. Đăng ký & Bảo mật tài khoản', icon: 'person_shield' },
  { id: 'booking-rules', title: '3. Quy định Đặt sân & Giữ chỗ', icon: 'event_available' },
  { id: 'payment', title: '4. Thanh toán & Đối soát giao dịch', icon: 'payments' },
  { id: 'court-rules', title: '5. Nội quy sân đấu & Trang phục', icon: 'sports_tennis' },
  { id: 'liability', title: '6. Trách nhiệm & Tài sản cá nhân', icon: 'health_and_safety' },
  { id: 'contact', title: '7. Liên hệ & Giải quyết khiếu nại', icon: 'support_agent' },
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
  // Check hash on load
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
          <span class="material-symbols-outlined icon-18">verified</span>
          <span>{{ $t('legal.badge') }}</span>
        </div>
        <h1 class="legal-title">{{ $t('legal.termsTitle') }}</h1>
        <p class="legal-subtitle">
          {{ $t('legal.termsSubtitle') }}
        </p>

        <!-- Navigation switcher -->
        <div class="doc-switcher">
          <span class="doc-tab active">
            <span class="material-symbols-outlined">description</span>
            <span>{{ $t('legal.tabTerms') }}</span>
          </span>
          <RouterLink to="/privacy" class="doc-tab">
            <span class="material-symbols-outlined">shield</span>
            <span>{{ $t('legal.tabPrivacy') }}</span>
          </RouterLink>
        </div>

        <div class="legal-meta-row">
          <div class="meta-item">
            <span class="material-symbols-outlined">update</span>
            <span>{{ $t('legal.updatedDate') }}</span>
          </div>
          <div class="meta-item">
            <span class="material-symbols-outlined">gavel</span>
            <span>{{ $t('legal.versionLabel') }}</span>
          </div>
          <div class="meta-item">
            <span class="material-symbols-outlined">sports_tennis</span>
            <span>{{ $t('legal.standardLabel') }}</span>
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
                <span class="material-symbols-outlined">headset_mic</span>
              </div>
              <h4>{{ $t('legal.supportTitle') }}</h4>
              <p>{{ $t('legal.supportDesc') }}</p>
              <a href="tel:0963792413" class="support-btn">
                <span class="material-symbols-outlined">call</span>
                <span>0963 792 413</span>
              </a>
            </div>
          </div>
        </aside>

        <!-- Main Document Body -->
        <main class="legal-body">
          <!-- Section 1 -->
          <section id="general" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">gavel</span>
              </div>
              <h2>1. Điều khoản chung &amp; Định nghĩa</h2>
            </div>
            <div class="section-text">
              <p>
                Chào mừng bạn đến với tổ hợp sân thể thao chuyên nghiệp <strong>Aura Court</strong>.
                Khi truy cập website, đăng ký tài khoản thành viên hoặc đặt khung giờ thi đấu, bạn
                xác nhận đã đọc, hiểu và cam kết tuân thủ toàn bộ các Điều khoản dịch vụ này.
              </p>
              <div class="definition-box">
                <h4>Các thuật ngữ chính:</h4>
                <ul>
                  <li>
                    <strong>"Aura Court" / "Chúng tôi":</strong> Đơn vị vận hành cụm sân cầu lông
                    tiêu chuẩn quốc tế và nền tảng công nghệ đặt lịch trực tuyến.
                  </li>
                  <li>
                    <strong>"Hội viên" / "Khách hàng" / "Bạn":</strong> Cá nhân, câu lạc bộ hoặc tổ
                    chức sử dụng nền tảng đặt sân hoặc sử dụng cơ sở vật chất của Aura Court.
                  </li>
                  <li>
                    <strong>"Khung giờ":</strong> Khoảng thời gian thi đấu 1h được xác nhận thành
                    công trên hệ thống. Một lần đặt sân gồm một hoặc nhiều khung giờ.
                  </li>
                  <li>
                    <strong>"Mã đặt sân" (Booking Code / QR):</strong> Chuỗi ký tự và mã QR định
                    danh duy nhất gửi đến quý khách để làm thủ tục check-in nhận sân.
                  </li>
                </ul>
              </div>
            </div>
          </section>

          <!-- Section 2 -->
          <section id="account" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">person_shield</span>
              </div>
              <h2>2. Đăng ký &amp; Bảo mật tài khoản</h2>
            </div>
            <div class="section-text">
              <p>
                Để tích lũy điểm thưởng hội viên, quản lý lịch đặt sân định kỳ và nhận thông báo ưu
                tiên, quý khách vui lòng đăng ký tài khoản <strong>Aura ID</strong>.
              </p>
              <ul>
                <li>
                  <strong>Tính chuẩn xác thông tin:</strong> Khách hàng cam kết cung cấp thông tin
                  liên hệ chính xác (Họ tên, Số điện thoại, Email) nhằm đảm bảo tính hợp lệ khi đối
                  soát thanh toán và xác minh danh tính nhận sân.
                </li>
                <li>
                  <strong>Trách nhiệm bảo mật:</strong> Quý khách có trách nhiệm tự bảo quản mật
                  khẩu tài khoản và thông báo ngay cho ban quản lý nếu phát hiện truy cập trái phép.
                </li>
                <li>
                  <strong>Quyền quản trị tài khoản:</strong> Aura Court bảo lưu quyền tạm khóa hoặc
                  chấm dứt tài khoản nếu phát hiện hành vi đầu cơ giờ sân, spam đơn ảo hoặc vi phạm
                  quy chế ứng xử tại sân.
                </li>
              </ul>
            </div>
          </section>

          <!-- Section 3 -->
          <section id="booking-rules" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">event_available</span>
              </div>
              <h2>3. Quy định Đặt sân &amp; Giữ chỗ</h2>
            </div>
            <div class="section-text">
              <p>
                Hệ thống đặt sân thông minh của Aura Court vận hành hoàn toàn tự động theo thời gian
                thực nhằm đảm bảo tính công bằng và minh bạch:
              </p>
              <div class="steps-flow">
                <div class="flow-step">
                  <span class="step-num">1</span>
                  <div>
                    <h5>Chọn sân &amp; khung giờ</h5>
                    <p>
                      Khách hàng lựa chọn cụm sân, loại sân (Sân Tiêu chuẩn hoặc VIP Arena) và khung
                      giờ thi đấu trống trên bảng lịch điện tử.
                    </p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-num">2</span>
                  <div>
                    <h5>Khóa tạm giữ chỗ (15 phút)</h5>
                    <p>
                      Hệ thống tự động bảo lưu khung giờ đã chọn trong 15 phút để bạn tiến hành quét
                      mã QR thanh toán an toàn.
                    </p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-num">3</span>
                  <div>
                    <h5>Xác nhận đơn &amp; Nhận mã QR vào sân</h5>
                    <p>
                      Khi giao dịch thanh toán thành công, hệ thống chuyển trạng thái sang
                      <em>"Đã xác nhận"</em> và tạo mã QR check-in tức thì.
                    </p>
                  </div>
                </div>
              </div>
              <div class="notice-callout">
                <span class="material-symbols-outlined">info</span>
                <span
                  >Lưu ý: Nếu sau 15 phút đơn hàng chưa được thanh toán, khung giờ sẽ tự động mở
                  khóa cho các hội viên khác đặt lịch.</span
                >
              </div>
              <div class="notice-callout">
                <span class="material-symbols-outlined">sell</span>
                <span
                  >Lịch đã thanh toán là lịch cuối cùng: Aura Court không hỗ trợ hủy, hoàn tiền hay
                  đổi lịch. Nếu không thể đến chơi, bạn có thể pass sân cho người khác trên Chợ
                  nhượng sân theo chính sách chuyển nhượng của cơ sở.</span
                >
              </div>
            </div>
          </section>

          <!-- Section 4 -->
          <section id="payment" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">payments</span>
              </div>
              <h2>4. Thanh toán &amp; Đối soát giao dịch</h2>
            </div>
            <div class="section-text">
              <p>
                Aura Court ứng dụng thanh toán không tiền mặt an toàn qua cổng
                <strong>VietQR / SePay</strong> tự động đối soát trong vài giây:
              </p>
              <ul>
                <li>
                  <strong>Nội dung chuyển khoản:</strong> Khi quét mã QR thanh toán, quý khách vui
                  lòng giữ nguyên nội dung chuyển khoản tự động (chứa mã đơn hàng) để hệ thống ghi
                  nhận đối soát tức thì.
                </li>
                <li>
                  <strong>Hóa đơn điện tử:</strong> Mọi biên nhận giao dịch được lưu trữ vĩnh viễn
                  và minh bạch trong mục "Lịch đặt sân" trên tài khoản cá nhân.
                </li>
                <li>
                  <strong>Hỗ trợ đối soát sai lệch:</strong> Trường hợp chuyển nhầm số tiền hoặc sai
                  mã giao dịch, vui lòng gửi ảnh chụp sao kê ngân hàng tới hotline hoặc quầy lễ tân
                  để được hỗ trợ đối soát thủ công nhanh nhất.
                </li>
              </ul>
            </div>
          </section>

          <!-- Section 5 -->
          <section id="court-rules" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">sports_tennis</span>
              </div>
              <h2>5. Nội quy sân đấu &amp; Trang phục thi đấu</h2>
            </div>
            <div class="section-text">
              <p>
                Để bảo vệ mặt thảm chuyên dụng BWF và duy trì môi trường thể thao văn minh, toàn bộ
                người chơi cần tuân thủ các quy định:
              </p>
              <div class="rules-grid">
                <div class="rule-box">
                  <span class="material-symbols-outlined rule-icon">do_not_step</span>
                  <h5>Giày chuyên dụng cầu lông</h5>
                  <p>
                    Bắt buộc sử dụng giày thể thao đế cao su Non-marking không để lại vệt màu.
                    Nghiêm cấm mang giày cao gót, đế đinh hoặc dép vào khu vực thảm thi đấu.
                  </p>
                </div>
                <div class="rule-box">
                  <span class="material-symbols-outlined rule-icon">smoke_free</span>
                  <h5>Không gian không khói thuốc</h5>
                  <p>
                    Nghiêm cấm tuyệt đối việc hút thuốc lá, thuốc lá điện tử hoặc sử dụng các chất
                    kích thích trong toàn bộ khuôn viên nhà thi đấu Aura Court.
                  </p>
                </div>
                <div class="rule-box">
                  <span class="material-symbols-outlined rule-icon">delete</span>
                  <h5>Giữ gìn vệ sinh chung</h5>
                  <p>
                    Vui lòng bỏ vỏ chai, quấn cán, ống cầu đã qua sử dụng vào thùng rác phân loại
                    sau khi kết thúc buổi tập luyện.
                  </p>
                </div>
                <div class="rule-box">
                  <span class="material-symbols-outlined rule-icon">timer</span>
                  <h5>Đúng giờ thi đấu</h5>
                  <p>
                    Có mặt trước 10 phút để khởi động và hoàn tất bàn giao sân đúng giờ khi hết lượt
                    thuê để không ảnh hưởng đến trận đấu tiếp theo.
                  </p>
                </div>
              </div>
            </div>
          </section>

          <!-- Section 6 -->
          <section id="liability" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">health_and_safety</span>
              </div>
              <h2>6. Trách nhiệm, An toàn &amp; Tài sản cá nhân</h2>
            </div>
            <div class="section-text">
              <ul>
                <li>
                  <strong>An toàn thể thao:</strong> Người chơi tự đánh giá tình trạng thể lực và tự
                  chịu trách nhiệm về an toàn sức khỏe khi tham gia các hoạt động vận động cường độ
                  cao. Aura Court trang bị sẵn tủ sơ cứu y tế sơ cấp cứu tại quầy lễ tân.
                </li>
                <li>
                  <strong>Tủ đồ locker thông minh:</strong> Aura Court trang bị hệ thống tủ đồ khóa
                  an toàn cho hội viên. Quý khách vui lòng tự bảo quản các vật phẩm giá trị cao
                  (trang sức, tiền mặt, thiết bị điện tử).
                </li>
                <li>
                  <strong>Trách nhiệm bảo vệ tài sản chung:</strong> Mọi hành vi cố ý phá hoại lưới
                  thi đấu, cột chắn, tường cách âm hoặc hệ thống đèn LED 800 Lux sẽ bị xử lý bồi
                  thường theo biểu giá quy định của ban quản lý.
                </li>
              </ul>
            </div>
          </section>

          <!-- Section 7 -->
          <section id="contact" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">support_agent</span>
              </div>
              <h2>7. Liên hệ, Chăm sóc hội viên &amp; Khiếu nại</h2>
            </div>
            <div class="section-text">
              <p>
                Mọi góp ý, thắc mắc về chất lượng dịch vụ hoặc yêu cầu hỗ trợ đặc biệt, xin vui lòng
                liên hệ với ban quản lý Aura Court qua các kênh:
              </p>

              <div class="contact-card-box">
                <div class="c-item">
                  <span class="material-symbols-outlined">call</span>
                  <div>
                    <strong>Hotline hỗ trợ:</strong>
                    <a href="tel:0963792413">0963 792 413</a>
                  </div>
                </div>
                <div class="c-item">
                  <span class="material-symbols-outlined">mail</span>
                  <div>
                    <strong>Email tiếp nhận:</strong>
                    <a href="mailto:ndviet.dev@gmail.com">ndviet.dev@gmail.com</a>
                  </div>
                </div>
                <div class="c-item">
                  <span class="material-symbols-outlined">location_on</span>
                  <div>
                    <strong>Trụ sở cụm sân:</strong>
                    <span>Tổ hợp Thể thao Aura Court, Hà Nội, Việt Nam</span>
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
  color: #d8b46e;
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

/* Custom Callout & Boxes */
.definition-box {
  background: var(--surface-subtle, #f7f3ed);
  border-left: 4px solid var(--accent, #c9a15a);
  border-radius: 0 14px 14px 0;
  padding: 18px 20px;
  margin: 18px 0;
}

:global(html.dark .definition-box) {
  background: #262024;
  border-left-color: #dfba72;
}

.definition-box h4 {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #1c1618);
  margin: 0 0 10px;
}

:global(html.dark .definition-box h4) {
  color: #faf7f3;
}

.definition-box ul {
  margin: 0;
}

.steps-flow {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin: 20px 0;
}

.flow-step {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 14px;
  padding: 16px 18px;
}

:global(html.dark .flow-step) {
  background: #262024;
  border-color: #382f35;
}

.step-num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--accent, #c9a15a);
  color: #1c1618;
  display: grid;
  place-items: center;
  font-size: 13px;
  font-weight: 750;
  flex-shrink: 0;
}

:global(html.dark .step-num) {
  background: rgba(223, 186, 114, 0.2);
  color: #dfba72;
  border: 1px solid rgba(223, 186, 114, 0.4);
}

.flow-step h5 {
  font-size: 14.5px;
  font-weight: 700;
  margin: 0 0 4px;
  color: var(--ink, #1c1618);
}

:global(html.dark .flow-step h5) {
  color: #faf7f3;
}

.flow-step p {
  font-size: 13px;
  color: var(--muted, #6e6668);
  margin: 0;
  line-height: 1.5;
}

:global(html.dark .flow-step p) {
  color: #a89fa4;
}

.notice-callout {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(201, 161, 90, 0.1);
  border: 1px solid rgba(201, 161, 90, 0.3);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 13px;
  color: var(--on-accent-container, #684f17);
  font-weight: 600;
}

:global(html.dark .notice-callout) {
  background: rgba(223, 186, 114, 0.12);
  border-color: rgba(223, 186, 114, 0.3);
  color: #dfba72;
}

/* Legal Table */
.table-responsive {
  overflow-x: auto;
  margin: 20px 0;
}

.legal-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 14px;
  overflow: hidden;
  font-size: 13.5px;
}

:global(html.dark .legal-table) {
  border-color: #382f35;
}

.legal-table th,
.legal-table td {
  padding: 14px 18px;
  text-align: left;
  border-bottom: 1px solid var(--border, #e8e1d7);
}

:global(html.dark .legal-table th),
:global(html.dark .legal-table td) {
  border-color: #382f35;
}

.legal-table th {
  background: var(--surface-subtle, #f7f3ed);
  font-weight: 700;
  color: var(--ink, #1c1618);
}

:global(html.dark .legal-table th) {
  background: #262024;
  color: #faf7f3;
}

.legal-table tbody tr:last-child td {
  border-bottom: none;
}

.badge-success {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(16, 185, 129, 0.15);
  color: #0e6b50;
  font-weight: 700;
  font-size: 12px;
}

:global(html.dark .badge-success) {
  background: rgba(16, 185, 129, 0.25);
  color: #34d399;
}

.badge-warning {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(245, 158, 11, 0.15);
  color: #b45309;
  font-weight: 700;
  font-size: 12px;
}

:global(html.dark .badge-warning) {
  background: rgba(245, 158, 11, 0.25);
  color: #fbbf24;
}

.badge-danger {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(239, 68, 68, 0.15);
  color: #b91c1c;
  font-weight: 700;
  font-size: 12px;
}

:global(html.dark .badge-danger) {
  background: rgba(239, 68, 68, 0.25);
  color: #f87171;
}

/* Rules Grid */
.rules-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-top: 18px;
}

.rule-box {
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 14px;
  padding: 18px 16px;
  transition:
    transform 0.2s ease,
    border-color 0.2s ease;
}

.rule-box:hover {
  transform: translateY(-2px);
  border-color: rgba(201, 161, 90, 0.4);
}

:global(html.dark .rule-box) {
  background: #262024;
  border-color: #382f35;
}

:global(html.dark .rule-box:hover) {
  border-color: rgba(223, 186, 114, 0.4);
}

.rule-icon {
  font-size: 26px;
  color: var(--accent, #c9a15a);
  margin-bottom: 8px;
  display: block;
}

:global(html.dark .rule-icon) {
  color: #dfba72;
}

.rule-box h5 {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #1c1618);
  margin: 0 0 6px;
}

:global(html.dark .rule-box h5) {
  color: #faf7f3;
}

.rule-box p {
  font-size: 12.5px;
  color: var(--muted, #6e6668);
  line-height: 1.5;
  margin: 0;
}

:global(html.dark .rule-box p) {
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
