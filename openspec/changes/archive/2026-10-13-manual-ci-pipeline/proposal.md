## Why

Mỗi lần push lên `main` hoặc `dev`, pipeline tự chạy hết các job kiểm tra trên runner macOS rồi deploy prod. Ngày 2026-10-13 người dùng chốt: pipeline chỉ chạy khi có người bấm chạy, push không được tự chạy hay tự deploy nữa.

## What Changes

- **BREAKING (quy trình):** push lên `main` không còn deploy prod. Chỉ bấm **Run pipeline** trên GitLab (nguồn `web`) hoặc chạy `glab ci run` / gọi API pipelines (nguồn `api`) mới tạo pipeline.
- Push, merge request và lịch chạy không tạo pipeline nữa.
- Pipeline chạy tay trên `main` hoặc `dev` vẫn kiểm tra trước, rồi build và deploy như khi push trước đây. Trên nhánh khác thì chỉ kiểm tra.
- Bỏ các luật đã thừa cho merge request và lịch chạy, gồm cả nút deploy tay trong pipeline merge request.

Phương án đã cân nhắc:
- **A. Chỉ tạo pipeline khi chạy tay (chọn).** Đúng yêu cầu, ít luật nhất. Muốn deploy thì chạy pipeline trên `main`.
- **B. Push vẫn tạo pipeline nhưng mọi job là `manual`.** Commit nào cũng có một pipeline treo chờ bấm. Phải bấm lần lượt ba job kiểm tra rồi mới tới deploy.
- **C. Giữ pipeline tự chạy cho merge request.** Khớp câu chữ spec cũ nhưng vẫn là tự chạy. Dự án cũng không dùng merge request mà merge thẳng vào `main`.

## Capabilities

### New Capabilities

Không có.

### Modified Capabilities

- `platform-readiness`: pipeline chỉ chạy khi có người bấm chạy. Cổng kiểm tra tĩnh và cổng kiểm thử trình duyệt không còn gắn với merge request hay lịch chạy.

## Impact

- `.gitlab-ci.yml`: `workflow.rules`, luật của bốn job, điều kiện gọi webhook Portainer.
- Quy trình: sau khi push `main` phải chạy pipeline trên `main` thì prod mới đổi (`glab ci run -b main` hoặc nút Run pipeline).
- Tài liệu: README (mục deploy). `direct-owner-cutover`: task 14.4, 15.4 và quyết định 13.
- Không đổi code ứng dụng, image hay cách deploy qua Portainer.
