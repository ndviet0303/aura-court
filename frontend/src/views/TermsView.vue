<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'

const activeSection = ref('general')

const sections = [
  { id: 'general', title: '1. Điều khoản chung & Định nghĩa', icon: 'gavel' },
  { id: 'account', title: '2. Đăng ký & Bảo mật tài khoản', icon: 'person_shield' },
  { id: 'booking-rules', title: '3. Quy định Đặt sân & Giữ chỗ', icon: 'event_available' },
  { id: 'payment', title: '4. Thanh toán & Đối soát giao dịch', icon: 'payments' },
  { id: 'refund-policy', title: '5. Đổi giờ, Hủy sân & Hoàn tiền', icon: 'published_with_changes' },
  { id: 'court-rules', title: '6. Nội quy sân đấu & Trang phục', icon: 'sports_tennis' },
  { id: 'liability', title: '7. Trách nhiệm & Tài sản cá nhân', icon: 'health_and_safety' },
  { id: 'contact', title: '8. Liên hệ & Giải quyết khiếu nại', icon: 'support_agent' },
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
          <span class="material-symbols-outlined">verified_user</span>
          <span>QUY ĐỊNH & PHÁP LÝ AURA COURT</span>
        </div>
        <h1 class="legal-title">Điều khoản dịch vụ</h1>
        <p class="legal-subtitle">
          Vui lòng đọc kỹ các điều khoản dưới đây trước khi sử dụng dịch vụ đặt sân trực tuyến và tham gia hoạt động thể thao tại Aura Court.
        </p>
        
        <!-- Navigation switcher -->
        <div class="doc-switcher">
          <span class="doc-tab active">
            <span class="material-symbols-outlined">description</span>
            <span>Điều khoản dịch vụ</span>
          </span>
          <RouterLink to="/privacy" class="doc-tab">
            <span class="material-symbols-outlined">shield</span>
            <span>Chính sách bảo mật</span>
          </RouterLink>
        </div>

        <div class="legal-meta-row">
          <div class="meta-item">
            <span class="material-symbols-outlined">update</span>
            <span>Cập nhật lần cuối: <strong>Tháng 08/2026</strong></span>
          </div>
          <div class="meta-item">
            <span class="material-symbols-outlined">gavel</span>
            <span>Phiên bản: <strong>2.4 (Áp dụng toàn quốc)</strong></span>
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
              <span>Mục lục điều khoản</span>
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
              <h4>Cần hỗ trợ trực tiếp?</h4>
              <p>Hotline ban quản lý phục vụ từ 06:00 – 23:00</p>
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
              <h2>1. Điều khoản chung & Định nghĩa</h2>
            </div>
            <div class="section-text">
              <p>
                Chào mừng bạn đến với hệ thống đặt sân thể thao chuyên nghiệp <strong>Aura Court</strong>. Khi truy cập website, tạo tài khoản hoặc đặt khung giờ thi đấu, bạn đồng ý tuân thủ và chịu ràng buộc bởi các Điều khoản dịch vụ này.
              </p>
              <div class="definition-box">
                <h4>Các thuật ngữ chính:</h4>
                <ul>
                  <li><strong>"Aura Court" / "Chúng tôi":</strong> Đơn vị vận hành hệ thống sân cầu lông và nền tảng công nghệ đặt sân.</li>
                  <li><strong>"Khách hàng" / "Bạn":</strong> Cá nhân, hội nhóm hoặc tổ chức sử dụng dịch vụ đặt sân hoặc sử dụng cơ sở vật chất của Aura Court.</li>
                  <li><strong>"Khung giờ" (Slot):</strong> Khoảng thời gian 60 phút hoặc liên tiếp được khách hàng lựa chọn để thi đấu tại một sân cụ thể.</li>
                  <li><strong>"Mã đặt sân" (Booking Code):</strong> Chuỗi ký tự duy nhất đại diện cho đơn đặt sân của khách hàng.</li>
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
              <h2>2. Đăng ký & Bảo mật tài khoản</h2>
            </div>
            <div class="section-text">
              <p>
                Để thuận tiện cho việc theo dõi lịch sử đặt sân, tích lũy điểm thưởng và nhận thông báo xác nhận tự động, khách hàng được khuyến khích đăng ký tài khoản thành viên.
              </p>
              <ul>
                <li><strong>Thông tin chính xác:</strong> Khách hàng cam kết cung cấp thông tin liên hệ (Họ tên, Email, Số điện thoại) chính xác để phục vụ cho việc đối soát giao dịch và liên lạc khi cần thiết.</li>
                <li><strong>Bảo mật mật khẩu:</strong> Bạn có trách nhiệm bảo mật mật khẩu của mình và chịu trách nhiệm cho mọi hoạt động diễn ra dưới tài khoản đã đăng ký.</li>
                <li><strong>Khóa tài khoản vi phạm:</strong> Aura Court bảo lưu quyền đình chỉ hoặc hủy tài khoản nếu phát hiện hành vi gian lận, spam đơn đặt sân hoặc vi phạm nghiêm trọng nội quy.</li>
              </ul>
            </div>
          </section>

          <!-- Section 3 -->
          <section id="booking-rules" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">event_available</span>
              </div>
              <h2>3. Quy định Đặt sân & Giữ chỗ</h2>
            </div>
            <div class="section-text">
              <p>
                Quy trình đặt sân trực tuyến được xây dựng tự động nhằm đảm bảo tính công bằng và minh bạch cho tất cả người chơi:
              </p>
              <div class="steps-flow">
                <div class="flow-step">
                  <span class="step-num">1</span>
                  <div>
                    <h5>Chọn sân & khung giờ</h5>
                    <p>Khách hàng lựa chọn ngày, sân thi đấu (Tiêu chuẩn hoặc VIP) và các khung giờ còn trống trên hệ thống.</p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-num">2</span>
                  <div>
                    <h5>Giữ chỗ tạm thời (15 phút)</h5>
                    <p>Hệ thống tự động khóa tạm khung giờ đã chọn trong 15 phút để bạn hoàn tất thủ tục thanh toán.</p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-num">3</span>
                  <div>
                    <h5>Xác nhận đơn đặt sân</h5>
                    <p>Ngay khi giao dịch thanh toán thành công, trạng thái lịch đặt chuyển sang <em>"Đã xác nhận"</em> và gửi mã QR nhận sân.</p>
                  </div>
                </div>
              </div>
              <div class="notice-callout">
                <span class="material-symbols-outlined">info</span>
                <span>Nếu sau 15 phút đơn hàng chưa được thanh toán, khung giờ sẽ tự động mở lại cho các khách hàng khác đặt.</span>
              </div>
            </div>
          </section>

          <!-- Section 4 -->
          <section id="payment" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">payments</span>
              </div>
              <h2>4. Thanh toán & Đối soát giao dịch</h2>
            </div>
            <div class="section-text">
              <p>
                Aura Court hỗ trợ thanh toán không dùng tiền mặt an toàn, thuận tiện thông qua cổng thanh toán QR Ngân hàng (SePay / VietQR) và thẻ nội địa.
              </p>
              <ul>
                <li><strong>Nội dung chuyển khoản chính xác:</strong> Khi quét mã QR thanh toán, quý khách vui lòng giữ nguyên nội dung chuyển khoản (chứa mã đơn hàng) để hệ thống tự động đối soát tức thì.</li>
                <li><strong>Hóa đơn & Chứng từ:</strong> Lịch sử thanh toán và biên nhận điện tử được lưu trữ vĩnh viễn trong mục "Lịch đặt sân" trên tài khoản cá nhân.</li>
                <li><strong>Xử lý sai sót:</strong> Trong trường hợp chuyển sai số tiền hoặc sai mã giao dịch, vui lòng giữ lại ảnh chụp sao kê ngân hàng và liên hệ ngay Hotline để thu ngân đối soát thủ công.</li>
              </ul>
            </div>
          </section>

          <!-- Section 5 -->
          <section id="refund-policy" class="content-card highlight-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">published_with_changes</span>
              </div>
              <h2>5. Chính sách Đổi giờ, Hủy sân & Hoàn tiền</h2>
            </div>
            <div class="section-text">
              <p>
                Để đảm bảo quyền lợi của người chơi và công tác vận hành sân, chính sách hủy và hoàn tiền được quy định cụ thể như sau:
              </p>
              
              <div class="table-responsive">
                <table class="legal-table">
                  <thead>
                    <tr>
                      <th>Thời gian yêu cầu hủy</th>
                      <th>Mức hoàn tiền</th>
                      <th>Hình thức hoàn</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Trước giờ thi đấu <strong>&ge; 24 tiếng</strong></td>
                      <td><span class="badge-success">Hoàn 100%</span></td>
                      <td>Chuyển khoản ngân hàng hoặc ví tài khoản</td>
                    </tr>
                    <tr>
                      <td>Trước giờ thi đấu <strong>từ 12 đến dưới 24 tiếng</strong></td>
                      <td><span class="badge-warning">Hoàn 70%</span></td>
                      <td>Bảo lưu điểm đặt sân cho lần tiếp theo</td>
                    </tr>
                    <tr>
                      <td>Trước giờ thi đấu <strong>dưới 12 tiếng</strong></td>
                      <td><span class="badge-danger">Không hoàn tiền</span></td>
                      <td>Hỗ trợ đăng lên sàn trao đổi giờ chơi</td>
                    </tr>
                    <tr>
                      <td>Sự cố bất khả kháng (Mất điện, bảo trì khẩn cấp)</td>
                      <td><span class="badge-success">Hoàn 100% + Đổi giờ ưu tiên</span></td>
                      <td>Hoàn tiền ngay trong ngày</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <!-- Section 6 -->
          <section id="court-rules" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">sports_tennis</span>
              </div>
              <h2>6. Nội quy sân đấu & Trang phục thi đấu</h2>
            </div>
            <div class="section-text">
              <p>
                Tất cả vận động viên và khách tham quan tại Aura Court cần chấp hành nghiêm túc các quy tắc sau:
              </p>
              <div class="rules-grid">
                <div class="rule-box">
                  <span class="material-symbols-outlined rule-icon">do_not_step</span>
                  <h5>Giày chuyên dụng cầu lông</h5>
                  <p>Bắt buộc mang giày đế cao su không để lại vết (Non-marking). Tuyệt đối không mang giày cao gót hoặc dép lê vào thảm sân.</p>
                </div>
                <div class="rule-box">
                  <span class="material-symbols-outlined rule-icon">smoke_free</span>
                  <h5>Khu vực không khói thuốc</h5>
                  <p>Nghiêm cấm hút thuốc lá, thuốc lá điện tử và sử dụng chất kích thích trong toàn bộ khuôn viên nhà thi đấu.</p>
                </div>
                <div class="rule-box">
                  <span class="material-symbols-outlined rule-icon">delete</span>
                  <h5>Vệ sinh môi trường</h5>
                  <p>Bỏ vỏ cầu, chai nước và rác thải vào thùng rác quy định sau mỗi trận đấu để giữ gìn không gian sạch đẹp.</p>
                </div>
                <div class="rule-box">
                  <span class="material-symbols-outlined rule-icon">timer</span>
                  <h5>Đúng giờ thi đấu</h5>
                  <p>Bàn giao sân đúng giờ khi hết khung giờ đăng ký để không làm ảnh hưởng đến lượt chơi tiếp theo.</p>
                </div>
              </div>
            </div>
          </section>

          <!-- Section 7 -->
          <section id="liability" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">health_and_safety</span>
              </div>
              <h2>7. Trách nhiệm & Tài sản cá nhân</h2>
            </div>
            <div class="section-text">
              <ul>
                <li><strong>An toàn thể thao:</strong> Cầu lông là môn thể thao vận động cường độ cao. Khách hàng tự chịu trách nhiệm về tình trạng sức khỏe và thể lực của bản thân khi tham gia tập luyện, thi đấu.</li>
                <li><strong>Tài sản cá nhân:</strong> Aura Court có bố trí tủ đồ khóa an toàn, tuy nhiên quý khách vui lòng tự bảo quản các vật phẩm giá trị cao (trang sức, ví tiền, điện thoại). Aura Court không chịu trách nhiệm đối với các mất mát tài sản cá nhân do sơ suất.</li>
                <li><strong>Bồi thường hư hại:</strong> Khách hàng có hành vi cố ý phá hoại lưới thi đấu, cột chắn, tường kính hoặc hệ thống đèn sẽ phải chịu chi phí sửa chữa, thay mới theo giá thị trường.</li>
              </ul>
            </div>
          </section>

          <!-- Section 8 -->
          <section id="contact" class="content-card">
            <div class="section-head">
              <div class="section-icon-box">
                <span class="material-symbols-outlined">support_agent</span>
              </div>
              <h2>8. Liên hệ & Giải quyết khiếu nại</h2>
            </div>
            <div class="section-text">
              <p>
                Mọi thắc mắc, phản ánh về chất lượng dịch vụ hoặc yêu cầu hỗ trợ hoàn hủy, quý khách có thể liên hệ trực tiếp với chúng tôi qua các kênh:
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
                    <strong>Địa chỉ trụ sở:</strong>
                    <span>Khu phức hợp thể thao Aura Court, Hà Nội, Việt Nam</span>
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

/* Custom Callout & Boxes */
.definition-box {
  background: var(--surface-subtle);
  border-left: 4px solid var(--pink-dark);
  border-radius: 0 14px 14px 0;
  padding: 18px 20px;
  margin: 18px 0;
}

html.dark .definition-box {
  border-left-color: #f8b4c4;
}

.definition-box h4 {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 10px;
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
  background: var(--surface-subtle);
  border-radius: 14px;
  padding: 16px 18px;
}

.step-num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--pink-dark);
  color: #ffffff;
  display: grid;
  place-items: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
}

.flow-step h5 {
  font-size: 14.5px;
  font-weight: 700;
  margin: 0 0 4px;
  color: var(--ink);
}

.flow-step p {
  font-size: 13px;
  color: var(--muted);
  margin: 0;
  line-height: 1.5;
}

.notice-callout {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(120, 85, 94, 0.08);
  border: 1px solid rgba(120, 85, 94, 0.2);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 13px;
  color: var(--pink-dark);
  font-weight: 500;
}

html.dark .notice-callout {
  background: rgba(255, 209, 220, 0.1);
  border-color: rgba(255, 209, 220, 0.2);
  color: #f8b4c4;
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
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  font-size: 13.5px;
}

.legal-table th,
.legal-table td {
  padding: 14px 18px;
  text-align: left;
  border-bottom: 1px solid var(--border);
}

.legal-table th {
  background: var(--surface-subtle);
  font-weight: 700;
  color: var(--ink);
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

html.dark .badge-success {
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

html.dark .badge-warning {
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

html.dark .badge-danger {
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
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 18px 16px;
}

.rule-icon {
  font-size: 26px;
  color: var(--pink-dark);
  margin-bottom: 8px;
}

html.dark .rule-icon {
  color: #f8b4c4;
}

.rule-box h5 {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 6px;
}

.rule-box p {
  font-size: 12.5px;
  color: var(--muted);
  line-height: 1.5;
  margin: 0;
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
