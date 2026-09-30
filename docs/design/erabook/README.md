# Erabook — Design system reference

Nguồn: [🎨 Design System](https://www.figma.com/design/LwmUj9WEuCOARHRu2xnu0X/Erabook?node-id=3-2) · Ngày lấy: 2026-09-29.

> **Bản lấy về chưa đầy đủ.** Figma MCP đã báo hết lượt gọi của gói Starter. Đây là bản tham chiếu, không phải file .fig, không phải bộ component Vue đã triển khai.

## Đã lưu

| Nội dung | Số lượng |
| --- | ---: |
| Component (gồm icon và từng variant) | 2.258 |
| Bộ biến thể được quan sát | 27 |
| Paint styles | 72 |
| Text styles | 44 |
| Effect styles | 6 |
| Variables / collections có trong file | 0 / 0 |

- `structure.xml`: nguyên văn cấu trúc XML-like do MCP trả về cho toàn trang; ID, tên, vị trí, kích thước. Component được biểu diễn ở mức metadata, không phải toàn bộ layer con.
- `components.json`: danh mục đầy đủ 2.258 component từ metadata, giữ nguyên tên và ID.
- `styles.json`: dữ liệu styles lấy trực tiếp từ Figma, giữ nguyên ID/key và số thực. `gridStyles: null` nghĩa là chưa lấy được, không phải không tồn tại.
- `colors.css`: 65 màu solid/transparent dưới dạng CSS custom properties có tiền tố `erabook`; chưa import vào ứng dụng. 7 gradient giữ nguyên trong JSON, không đoán góc chuyển màu.
- `colors.png`: ảnh xuất của khu vực Colors.
- `manifest.json`: phạm vi, số lượng, trạng thái và phần còn thiếu.
- `svg/`: bốn bản SVG người dùng xuất từ Figma, giữ nguyên tên file và toàn bộ nội dung; không tối ưu, tách layer hay thay font.

## Icon và component đã tách

- `icons/`: **1.950 icon**, chia thư mục theo tên Figma, ví dụ `iconly/regular/bold/`.
- `components/`: **189 component/variant**, chia theo nhóm Input, Button, Avatar…
- `elements/`: **119 element/variant**, chia theo nhóm của bản thiết kế.
- Mỗi mục có **SVG độc lập + JSON cùng tên**; hậu tố ID Figma tránh trùng tên.
- [assets-index.json](assets-index.json): tra cứu toàn bộ 2.258 mục bằng `nodeId`, tên, nhóm, SVG và metadata.
- [assets-preview.png](assets-preview.png): ảnh kiểm tra mẫu; [asset-verification.json](asset-verification.json): kết quả kiểm tra tất cả mục.

JSON giữ `fileKey`, `nodeId`, tên gốc, `parentId`, toàn bộ chuỗi `hierarchy`, `bounds` trong SVG nguồn, `renderBounds`, tên variant được phân tích từ tên component, nguồn và SHA-256. Đây là định dạng tham chiếu gần dữ liệu MCP, **không phải phản hồi MCP nguyên bản**. `variantsFromName` chỉ lấy từ tên, không giả lập `componentPropertyDefinitions`.

Cách tách: cộng tọa độ các ancestor trong `structure.xml`, đối chiếu hình học SVG bằng Chromium, giữ thứ tự vẽ và các group/mask/clip-path/gradient/ảnh nhúng được tham chiếu. Không mang cả board vào mỗi SVG, không raster hóa hay vẽ lại icon. Bản SVG gốc, `components.json`, `structure.xml` và `styles.json` được giữ nguyên.

Giới hạn: SVG đã làm phẳng nên không khôi phục được layer ownership, Auto Layout, text thật hay thuộc tính component. Chỉ lấy vùng render của từng mục; hiệu ứng vượt ra ngoài vùng này có thể bị cắt. Divider có chiều cao metadata 0 được xuất cao 1 px để giữ đường stroke; avatar Live `1132:36982` được mở rộng tới 54 × 66 để giữ nhãn và viền ngoài. Bảy mục có cảnh báo hình học trong JSON nhưng vẫn được kiểm tra ảnh trong vùng xuất. Chữ vẫn là vector; font Be Vietnam Pro chỉ là đề xuất khi triển khai UI thật.

Kiểm tra ảnh: so toàn bộ 2.258 vùng với SVG gốc trên nền trắng, dung sai 24 mỗi kênh màu và ngưỡng lỗi 1% pixel. Hai wrapper backdrop blur ở keyboard được giữ trong SVG nhưng loại khỏi **cả hai ảnh kiểm tra**, vì Chromium không cho đọc pixel canvas chứa `foreignObject`; hiệu ứng blur này không được xác minh bằng phép so pixel.

Tái tạo và kiểm tra (dùng Playwright/Chromium đã có trong dự án, không thêm dependency):

```sh
node docs/design/erabook/extract-assets.mjs
node docs/design/erabook/extract-assets.mjs --check
node docs/design/erabook/check-assets.mjs
```

Lệnh đầu tái tạo các file SVG/JSON đã sinh; sửa nguồn thay vì sửa trực tiếp output. `--check` chỉ đọc và đối chiếu dữ liệu đã lưu. Lệnh cuối cập nhật báo cáo/ảnh kiểm tra.

## SVG xuất thủ công

Đối chiếu kích thước với các khu vực đã lấy qua MCP (SVG là phần Content, không gồm header của khu vực):

| File | Khu vực tương ứng | Kích thước |
| --- | --- | --- |
| [Content.svg](svg/Content.svg) | Iconography | 1968 × 11828 |
| [Content (1).svg](<svg/Content (1).svg>) | Colors | 1700 × 4984 |
| [Content (2).svg](<svg/Content (2).svg>) | Components & Variants | 1715 × 6283 |
| [Content (3).svg](<svg/Content (3).svg>) | Elements | 2070 × 5533 |

Cả bốn giữ cấu trúc SVG gốc, vector và ảnh nhúng, không phụ thuộc URL ảnh bên ngoài. Chữ đã chuyển thành path: không còn text/font có thể chỉnh trực tiếp. Đây là bản tham chiếu hình ảnh, không thay thế cấu trúc Auto Layout hay định nghĩa variant gốc của Figma.

Font đề xuất cho giao diện tiếng Việt khi triển khai: [Be Vietnam Pro](https://fonts.google.com/specimen/Be+Vietnam+Pro), weight 400 cho nội dung, 500 cho nhãn/nút, 600–700 cho tiêu đề. Chưa thay font trong ứng dụng; `styles.json` vẫn giữ dữ liệu nguồn.

## Các khu vực

| Khu vực | Node | Phạm vi |
| --- | --- | --- |
| Colors | 430:2166 | Bảng màu |
| Typography | 430:2167 | Thang chữ |
| Iconography | 430:2959 | 1.950 component icon |
| Components & Variants | 432:3907 | 207 component/component-set |
| Elements | 560:15288 | 128 component/component-set |
| My Profile | 1140:18595 | Thông tin tác giả |

Tổng 1.950 + 207 + 128 = 2.285 đối tượng component/component-set, gồm 2.258 component và 27 set.

## Còn thiếu / giới hạn

- 1 grid style: đã xác nhận tồn tại nhưng chưa lấy được giá trị.
- 26/44 text style không trả về `fontName`; giữ nguyên chỗ thiếu, không suy đoán. Những style có font metadata dùng Urbanist.
- Thuộc tính chi tiết của từng layer/component (padding, gap, radius, fills, text segments, component properties…) chưa lưu đầy đủ.
- Đã bổ sung SVG tổng thể cho Colors, Iconography, Components & Variants và Elements; đã tách toàn bộ icon/component trong danh mục. Ảnh cần thiết được nhúng trong SVG, chưa xuất riêng ảnh nguồn; chưa có preview Typography và My Profile.
- Không lấy các màn hình sản phẩm ở trang “🔆 Light Theme”: yêu cầu hiện tại là design system.
- Không sửa file Figma, không đổi theme/giao diện ứng dụng, không thêm thư viện.

Quyết định: lưu snapshot tham chiếu độc lập thay vì áp dụng ngay vào giao diện; yêu cầu là lấy dữ liệu về, chưa yêu cầu tích hợp. Khi có lại quyền gọi MCP, tiếp tục các phần thiếu trong `manifest.json`, không cần lấy lại styles đã đủ.

## Màu

| Figma style | Giá trị |
| --- | --- |
| Primary/500 | #f89300 |
| Primary/400 | #f9a933 |
| Primary/300 | #fbbe66 |
| Primary/200 | #fcd499 |
| Primary/100 | #fef4e6 |
| Secondary/500 | #ffc107 |
| Secondary/400 | #ffcd39 |
| Secondary/300 | #ffda6a |
| Secondary/200 | #ffe69c |
| Secondary/100 | #fff9e6 |
| Alerts & Status/Success | #12d18e |
| Alerts & Status/Info | #f89300 |
| Alerts & Status / Warning | #facc15 |
| Alerts & Status / Error | #f75555 |
| Alerts & Status / Disabled | #d8d8d8 |
| Alerts & Status/Button Disabled | #c67600 |
| Greyscale / 900 | #212121 |
| Greyscale / 800 | #424242 |
| Greyscale / 700 | #616161 |
| Greyscale / 600 | #757575 |
| Greyscale / 500 | #9e9e9e |
| Greyscale / 400 | #bdbdbd |
| Greyscale / 300 | #e0e0e0 |
| Greyscale / 200 | #eeeeee |
| Greyscale / 100 | #f5f5f5 |
| Greyscale / 50 | #fafafa |
| Gradients/Gradident Orange | Gradient — xem styles.json |
| Gradients/Gradient Green | Gradient — xem styles.json |
| Gradients/Gradient Yellow | Gradient — xem styles.json |
| Gradients/Gradient Blue | Gradient — xem styles.json |
| Gradients/Gradient Purple | Gradient — xem styles.json |
| Gradients/Gradient Teal | Gradient — xem styles.json |
| Gradients/Gradient Red | Gradient — xem styles.json |
| Others / White | #ffffff |
| Others / Black | #000000 |
| Others / Red | #f54336 |
| Others / Pink | #ea1e61 |
| Others / Purple | #9d28ac |
| Others / Deep Purple | #673ab3 |
| Others / Indigo | #3f51b2 |
| Others / Blue | #1a96f0 |
| Others / Light Blue | #00a9f1 |
| Others / Cyan | #00bcd3 |
| Others / Teal | #009689 |
| Others / Green | #4aaf57 |
| Others / Light Green | #8bc255 |
| Others / Lime | #cddc4c |
| Others / Yellow | #ffeb4f |
| Others / Amber | #ffc02d |
| Others / Orange | #ff981f |
| Others / Deep Orange | #ff5726 |
| Others / Brown | #7a5548 |
| Others / Blue Grey | #607d8a |
| Dark/Dark 1 | #181a20 |
| Dark/Dark 2 | #1f222a |
| Dark/Dark 3 | #1f222a |
| Dark/Dark 4 | #35383f |
| Background/Orange | #fff8ee |
| Background/Green | #f1fdf5 |
| Background/Yellow | #fffceb |
| Background/Blue | #f6f9ff |
| Background/Purple | #f9f8ff |
| Background/Teal | #f2fffd |
| Background/Red | #fff7f8 |
| Transparent/Orange | rgba(248, 147, 0, 0.08) |
| Transparent/Green | rgba(27, 172, 75, 0.08) |
| Transparent/Yellow | rgba(255, 211, 0, 0.08) |
| Transparent/Blue | rgba(36, 107, 253, 0.08) |
| Transparent/Purple | rgba(105, 73, 255, 0.08) |
| Transparent/Teal | rgba(1, 155, 131, 0.08) |
| Transparent/Red | rgba(255, 90, 95, 0.08) |
| Transparent/Cyan | rgba(0, 188, 212, 0.08) |

## Typography

Đơn vị cỡ chữ là px; line height và letter spacing giữ theo đơn vị của Figma. Số trong bảng được làm tròn để đọc, JSON giữ giá trị gốc.

| Style | Cỡ | Font trả về | Line height |
| --- | ---: | --- | --- |
| H1/bold | 48 | Chưa có metadata | 160% |
| H2/bold | 40 | Chưa có metadata | 160% |
| H3/bold | 32 | Chưa có metadata | 160% |
| H4/bold | 24 | Chưa có metadata | 160% |
| H5/bold | 20 | Chưa có metadata | 160% |
| H6/bold | 18 | Chưa có metadata | 160% |
| body/xlarge/bold | 18 | Chưa có metadata | 140% |
| body/xlarge/semibold | 18 | Chưa có metadata | 140% |
| body/xlarge/medium | 18 | Chưa có metadata | 140% |
| body/xlarge/regular | 18 | Chưa có metadata | 140% |
| body/large/bold | 16 | Chưa có metadata | 140% |
| body/large/semibold | 16 | Chưa có metadata | 140% |
| body/large/medium | 16 | Chưa có metadata | 140% |
| body / large / regular | 16 | Chưa có metadata | 140% |
| body/medium/bold | 14 | Chưa có metadata | 140% |
| body / medium / semibold | 14 | Chưa có metadata | 140% |
| body/medium/medium | 14 | Chưa có metadata | 140% |
| body/medium/regular | 14 | Chưa có metadata | 140% |
| body/small/bold | 12 | Chưa có metadata | auto |
| body / small / semibold | 12 | Chưa có metadata | auto |
| body / small / medium | 12 | Chưa có metadata | auto |
| body/small/regular | 12 | Chưa có metadata | auto |
| body/xsmall/bold | 10 | Chưa có metadata | auto |
| body / xsmall / semibold | 10 | Chưa có metadata | auto |
| body / xsmall / medium | 10 | Chưa có metadata | auto |
| H1/semibold | 48 | Urbanist SemiBold | 160% |
| H1/medium | 48 | Urbanist Medium | 160% |
| H1/regular | 48 | Urbanist Regular | 160% |
| H2/semibold | 40 | Urbanist SemiBold | 160% |
| H2/medium | 40 | Urbanist Medium | 160% |
| H2/regular | 40 | Urbanist Regular | 160% |
| H3/semibold | 32 | Urbanist SemiBold | 160% |
| H3/medium | 32 | Urbanist Medium | 160% |
| H3/regular | 32 | Urbanist Regular | 160% |
| H4/semibold | 24 | Urbanist SemiBold | 160% |
| H4/medium | 24 | Urbanist Medium | 160% |
| H4/regular | 24 | Urbanist Regular | 160% |
| H5/semibold | 20 | Urbanist SemiBold | 160% |
| H5/medium | 20 | Urbanist Medium | 160% |
| H5/regular | 20 | Urbanist Regular | 160% |
| H6/semibold | 18 | Urbanist SemiBold | 160% |
| H6/medium | 18 | Urbanist Medium | 160% |
| H6/regular | 18 | Urbanist Regular | 160% |
| body/xsmall/regular | 10 | Chưa có metadata | auto |

## Hiệu ứng

| Style | X / Y | Blur / Spread | Màu / Alpha |
| --- | --- | --- | --- |
| Card/Shadow 1 | 0 / 4 | 60 / 0 | #04060f / 0.08 |
| Card/Shadow 2 | 0 / 4 | 60 / 0 | #04060f / 0.05 |
| Card/Shadow 3 | 0 / 20 | 100 / 0 | #04060f / 0.08 |
| Button/Shadow 1 | 4 / 8 | 24 / 0 | #f89300 / 0.25 |
| Button/Shadow 2 | 4 / 12 | 32 / 0 | #f89300 / 0.2 |
| Button/Shadow 3 | 4 / 16 | 32 / 0 | #f89300 / 0.2 |
