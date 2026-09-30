# Bảng ánh xạ Design System Erabook V2 (Erabook Design Asset & Token Mapping)

Tài liệu thiết lập ánh xạ chuẩn giữa các token màu, typography, icon và component từ bộ thiết kế Erabook gốc (`docs/design/erabook/`) sang các thành phần UI thực tế của Aura Court Frontend, theo yêu cầu nhiệm vụ 1.4 và nguyên tắc thiết kế D4/D5.

---

## 1. Nguồn gốc, Bản quyền & Phông chữ (Provenance & Font Availability)

| Tài nguyên | Nguồn gốc / Giấy phép | Tình trạng khả dụng | Ghi chú tương thích |
| :--- | :--- | :--- | :--- |
| **Phông chữ: Be Vietnam Pro** | Google Fonts / SIL Open Font License 1.1 | Khả dụng (WOFF2 tự lưu và CDN fallback) | Hỗ trợ 100% tiếng Việt có dấu và Latin. Tải các trọng số: Regular (400), Medium (500), SemiBold (600), Bold (700). Chỉ kích hoạt khi `[data-ui-version="v2"]`, không làm ảnh hưởng phông Inter và Material Symbols của V1. |
| **Biểu tượng: Iconly Icons** | Trích xuất từ Figma Node `430:2959` (`docs/design/erabook/icons/`) | Đã trích xuất độc lập SVG + metadata JSON | Chọn lọc tập con cần dùng (Home, Calendar, Search, User, Filter, Arrow, Check, Close, Wallet, etc.). Không nhúng toàn bộ 1.950 SVG vào bundle. SVG đã được làm sạch `id` và gradient xung đột. |
| **Bảng màu: Erabook Palette** | Trích xuất từ Figma Node `430:2166` (`docs/design/erabook/colors.css`) | Khả dụng (72 paint styles) | Giữ nguyên các giá trị hex gốc, ánh xạ có kiểm soát độ tương phản (contrast >= 4.5:1 với chữ, >= 3:1 với viền/trạng thái). |
| **Snapshot tham chiếu** | `docs/design/erabook/` | Bất biến (Read-only reference) | Không chỉnh sửa các file snapshot, SVG manual exports hay JSON metadata trong thư mục gốc `docs/design/erabook/`. |

---

## 2. Ánh xạ Token Màu & Semantic CSS Variables

### 2.1 Bảng màu chính (Brand & Surface Tokens)

| Semantic Token | V1 Light (Warm Ivory/Burgundy) | V1 Dark (Luxury Velvet/Gold) | V2 Light (Erabook Light) | V2 Dark (Erabook Dark) | Erabook Source Node & Hex | Trạng thái / Adapted |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `--bg` / `--background` | `#FAF7F3` | `#141113` | `#FFFFFF` | `#181A20` | `Dark/Dark 1` (`181A20`), `Others/White` (`FFFFFF`) | Nguyên bản |
| `--surface` | `#FFFFFF` | `#1E191C` | `#FAFAFA` | `#1F222A` | `Greyscale/50` (`FAFAFA`), `Dark/Dark 2` (`1F222A`) | Nguyên bản |
| `--surface-card` | `#FFFFFF` | `#261F24` | `#FFFFFF` | `#1F222A` | `Others/White`, `Dark/Dark 2` | Adapted (viền card) |
| `--ink` / `--text` | `#2D2426` | `#FAF7F3` | `#212121` | `#FFFFFF` | `Greyscale/900` (`212121`), `Others/White` | Nguyên bản |
| `--muted` / `--text-muted` | `#635759` | `#A89FA4` | `#757575` | `#9E9E9E` | `Greyscale/600` (`757575`), `Greyscale/500` (`9E9E9E`) | Nguyên bản |
| `--primary` | `#6B2D3F` | `#E58E9F` | `#F89300` | `#F89300` | `Primary/500` (`F89300`) | Nguyên bản |
| `--primary-hover` | `#532331` | `#F0A8B6` | `#E08500` | `#FFA726` | `Primary/600` (`E08500` - adapted), `Primary/400` (`F9A933`) | Adapted tương phản |
| `--on-primary` | `#FFFFFF` | `#141113` | `#FFFFFF` | `#181A20` | Độ tương phản trắng trên cam đạt 2.6:1 (large text) hoặc dùng `#212121` (5.2:1) cho body/actions | Adapted tiêu chuẩn 4.5:1 |
| `--secondary` | `#C9A15A` | `#D8B46E` | `#FFC107` | `#FFC107` | `Secondary/500` (`FFC107`) | Nguyên bản |
| `--accent` | `#C9A15A` | `#D8B46E` | `#FFC107` | `#FFC107` | Đồng nhất `--secondary` | Nguyên bản |
| `--border` | `#E8E1D7` | `#332B30` | `#EEEEEE` | `#35383F` | `Greyscale/200` (`EEEEEE`), `Dark/Dark 4` (`35383F`) | Nguyên bản |
| `--border-focus` | `#6B2D3F` | `#D8B46E` | `#F89300` | `#F89300` | `Primary/500` | Focus ring rõ ràng |

### 2.2 Semantic Feedback & Status Tokens

| Trạng thái | Semantic Token | V2 Giá trị | Erabook Style Name & Hex | Ghi chú tiếp cận (Accessibility) |
| :--- | :--- | :--- | :--- | :--- |
| **Thành công (Success)** | `--color-success` | `#12D18E` | `Alerts & Status/Success` (`#12D18E`) | Kèm icon check hoặc nhãn văn bản |
| **Thông tin (Info)** | `--color-info` | `#F89300` | `Alerts & Status/Info` (`#F89300`) | Kèm icon info |
| **Cảnh báo (Warning)** | `--color-warning` | `#FACC15` | `Alerts & Status/Warning` (`#FACC15`) | Kèm icon warning |
| **Lỗi (Error)** | `--color-error` | `#F75555` | `Alerts & Status/Error` (`#F75555`) | Kèm thông báo lỗi chi tiết |
| **Vô hiệu hóa (Disabled)** | `--color-disabled` | `#D8D8D8` | `Alerts & Status/Disabled` (`#D8D8D8`) | `aria-disabled="true"`, cursor not-allowed |

---

## 3. Ánh xạ Component & Primitives

| Component Aura Court | Erabook Reference Component | Node ID nguồn | Quy cách thích ứng (Adapted Parameters) |
| :--- | :--- | :--- | :--- |
| **`AuraButton` (Primary)** | `Type=Button, Type 2=Primary, Type 3=Filled` | `119:15` | Radius: 100px (pill) hoặc 12px; Padding: 12px 24px; Nền: `#F89300`; Chữ: Trắng hoặc Đậm `#212121` tùy kích thước. Shadow: `0 8px 24px rgba(248, 147, 0, 0.25)`. |
| **`AuraButton` (Secondary)** | `Type=Button, Type 2=Secondary, Type 3=Filled` | `119:20`, `442:1562` | Nền: `rgba(248, 147, 0, 0.1)` (Light) / `#1F222A` (Dark); Viền: `#F89300` hoặc không viền; Chữ: `#F89300`. |
| **`AuraUiVersionSwitch`** | `State=Enabled, Theme=Default, Component=Toggle` | `247:2198`, `442:2267` | Width: 48px, Height: 26px, Knob: 20px. Nền On: `#F89300`; Nền Off: `#EEEEEE` (Light) / `#35383F` (Dark). Không submit form, focus visible rõ ràng. |
| **Form Input / Select** | `Component=Input, State=Default / Active` | `433:783`, `442:1464` | Radius: 12px; Nền: `#FAFAFA` (Light) / `#1F222A` (Dark); Viền: `#EEEEEE` (Light) / `#35383F` (Dark); Focus border: `#F89300`. |
| **Card Surface** | `elements-book-card` | `2138:28046`, `2138:28051` | Radius: 16px; Nền: `#FFFFFF` (Light) / `#1F222A` (Dark); Shadow: `0 4px 60px rgba(4, 6, 15, 0.05)`. |
| **Modal / Dialog** | `Component=Modal` | `docs/design/erabook/components/modal/` | Radius: 24px; Nền: `#FFFFFF` (Light) / `#1F222A` (Dark); Backdrop: `rgba(0, 0, 0, 0.6)`. |
| **Tag / Badge** | `Component=Tag / Chips` | `docs/design/erabook/components/tag/` | Radius: 8px; Nền cam nhạt `rgba(248, 147, 0, 0.12)`, chữ `#F89300`. |

---

## 4. Tập con Iconly sử dụng cho V2 (Iconly Subset)

| Icon Name | Nghĩa sử dụng trong Aura Court | Đường dẫn tham chiếu trong Erabook | Counterpart trong V1 (Material Symbols) |
| :--- | :--- | :--- | :--- |
| `icon-home` | Trang chủ | `icons/iconly/regular/bulk/home--...` | `home` |
| `icon-calendar` | Lịch / Ngày đặt sân | `icons/iconly/regular/bold/calendar--...` | `calendar_today` |
| `icon-search` | Tìm kiếm sân | `icons/iconly/regular/light/search--...` | `search` |
| `icon-profile` / `icon-user` | Hồ sơ người dùng | `icons/iconly/regular/bulk/profile--...` | `person` |
| `icon-filter` | Bộ lọc sân | `icons/iconly/regular/bold/filter--...` | `tune` / `filter_list` |
| `icon-arrow-left` | Quay lại | `icons/iconly/regular/light/arrow---left--...` | `arrow_back` |
| `icon-arrow-right` | Tiếp tục | `icons/iconly/regular/light/arrow---right--...` | `arrow_forward` |
| `icon-check` | Xác nhận / Đã chọn | `additional-icons/icon-check--...` | `check` |
| `icon-close` / `icon-times` | Đóng / Bỏ chọn | `additional-icons/icon-times--...` | `close` |
| `icon-wallet` | Ví tiền / Điểm | `icons/iconly/regular/bulk/wallet--...` | `account_balance_wallet` |
| `icon-location` | Vị trí / Bản đồ sân | `icons/iconly/regular/bold/location--...` | `location_on` |
| `icon-time-circle` | Giờ chơi / Thời gian | `icons/iconly/regular/light/time-circle--...` | `schedule` |
| `icon-star` | Đánh giá sân | `icons/iconly/regular/bold/star--...` | `star` |

---

## 5. Quy chuẩn Kỹ thuật (Implementation Adaptation Rules)

1. **Không sao chép Text dạng Path**: Toàn bộ chữ trong SVG tham chiếu Erabook là vector curves (outline text). Khi triển khai vào component Vue, **bắt buộc** dùng HTML text thật với `v-text` / `{{ $t(...) }}` để đảm bảo accessibility, i18n tiếng Việt/tiếng Anh và selectability.
2. **Auto Layout thành CSS Flexbox & Grid**: Vì SVG không giữ cấu trúc Auto Layout của Figma, mọi khoảng cách (padding, gap, alignment) được ánh xạ sang Flexbox/Grid hiện đại với đơn vị `px` hoặc `rem` chuẩn hóa.
3. **Độc lập với Icon Font V1**: Các icon của Material Symbols trong V1 tiếp tục được giữ nguyên khi ở chế độ V1. Ở V2, hệ thống ưu tiên hiển thị các biểu tượng Iconly được đóng gói dưới dạng SVG component an toàn.
