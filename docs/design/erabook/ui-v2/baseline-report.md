# Báo cáo Ảnh chuẩn Baseline V1 (V1 Light/Dark Visual Baseline Report)

Báo cáo lưu trữ thông tin kiểm thử ảnh chuẩn (visual baseline) của giao diện Aura Court V1 (Premium Club) trước khi triển khai các thay đổi của Erabook V2, nhằm phục vụ đối chiếu hồi quy thị giác theo quyết định D7 và nhiệm vụ 1.3.

---

## 1. Môi trường & Phiên bản chụp (Environment & Revision)

- **Git Revision**: `42d56ee` (nhánh `main`)
- **Ngày thực hiện**: 2026-09-29
- **Môi trường thực thi**: Node.js `v24.12.0`, Vite `v8.2.0` (production preview), Chromium headless via Playwright
- **Độ phân giải màn hình (Viewports)**:
  - Desktop: `1440 × 900`
  - Mobile: `390 × 844` (chuẩn iPhone 12/13/14)
- **Ngôn ngữ thử nghiệm**: Tiếng Việt (`vi-VN`)

---

## 2. Danh mục ảnh chuẩn đã lưu trữ (`docs/design/erabook/ui-v2/baseline/`)

Toàn bộ 24 ảnh chụp được lưu trữ tại `docs/design/erabook/ui-v2/baseline/`:

| Màn hình đại diện | Tuyến đường (Route) | File Light Desktop (1440px) | File Dark Desktop (1440px) | File Light Mobile (390px) | File Dark Mobile (390px) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Trang chủ (Home)** | `/` | `v1-home-light-desktop-1440.png` | `v1-home-dark-desktop-1440.png` | `v1-home-light-mobile-390.png` | `v1-home-dark-mobile-390.png` |
| **Danh sách sân (Courts)** | `/courts` | `v1-courts-light-desktop-1440.png` | `v1-courts-dark-desktop-1440.png` | `v1-courts-light-mobile-390.png` | `v1-courts-dark-mobile-390.png` |
| **Chi tiết sân (Detail)** | `/courts/:slug` | `v1-court-detail-light-desktop-1440.png` | `v1-court-detail-dark-desktop-1440.png` | `v1-court-detail-light-mobile-390.png` | `v1-court-detail-dark-mobile-390.png` |
| **Lịch đặt sân (Schedule)**| `/courts/:slug/schedule` | `v1-schedule-light-desktop-1440.png` | `v1-schedule-dark-desktop-1440.png` | `v1-schedule-light-mobile-390.png` | `v1-schedule-dark-mobile-390.png` |
| **Đăng nhập (Login)** | `/login` | `v1-login-light-desktop-1440.png` | `v1-login-dark-desktop-1440.png` | `v1-login-light-mobile-390.png` | `v1-login-dark-mobile-390.png` |
| **Điều khoản (Terms)** | `/terms` | `v1-terms-light-desktop-1440.png` | `v1-terms-dark-desktop-1440.png` | `v1-terms-light-mobile-390.png` | `v1-terms-dark-mobile-390.png` |

---

## 3. Đặc điểm thị giác V1 & Các khiếm khuyết có sẵn (Pre-existing Observations)

Để tránh nhầm lẫn giữa lỗi hồi quy của V2 với các đặc điểm hiện hữu của V1:

1. **Bảng màu Luxury Premium Club V1**:
   - Light Mode: Nền ngà ấm `#FAF7F3`, màu thương hiệu Deep Burgundy `#6B2D3F`, điểm nhấn Antique Gold `#C9A15A`.
   - Dark Mode: Nền nhung tối `#141113`, màu thương hiệu Rose Velvet `#E58E9F`, điểm nhấn Luminous Gold `#D8B46E`.
   - Nhiều thành phần CSS cũ (`variables.css`, `buttons.css`, `NavbarUserProfile.vue`) còn hardcode màu Burgundy và Gold trực tiếp hoặc dùng `!important`.
2. **Typography V1**:
   - Đang sử dụng phông chữ Inter và biểu tượng Google Material Symbols.
   - Chưa tích hợp phông chữ Be Vietnam Pro.
3. **Thanh điều hướng & Menu**:
   - Menu profile (`NavbarUserProfile.vue`) và ngăn kéo mobile (`NavbarMobileDrawer.vue`) hiện chỉ có công tắc đổi màu sáng/tối và chuyển ngôn ngữ; **chưa có** công tắc chuyển phiên bản giao diện `AuraUiVersionSwitch`.
4. **Xử lý lưu trữ trình duyệt**:
   - `stores/theme.ts` đọc/ghi trực tiếp `localStorage.getItem('aura_theme')` và `localStorage.setItem('aura_theme', ...)` mà chưa có `try / catch` bao bọc, có nguy cơ gây crash ứng dụng nếu người dùng chặn cookie/storage (SecurityError).
5. **Hiển thị trên Mobile (390px)**:
   - Các bảng chọn giờ và lưới lịch ở Schedule View cần thanh cuộn ngang để thao tác trọn vẹn.
