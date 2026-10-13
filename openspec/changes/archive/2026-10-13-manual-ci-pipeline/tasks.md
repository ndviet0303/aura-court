## 1. Pipeline chạy tay

- [x] 1.1 `.gitlab-ci.yml`: `workflow.rules` chỉ nhận nguồn `web` và `api`.
- [x] 1.2 Bỏ các luật đã thừa:
  - `validate_rules` của ba job kiểm tra;
  - luật merge request và lịch chạy của `build_and_deploy`, job này chỉ còn luật nhánh `main`/`dev`;
  - điều kiện merge request trước webhook Portainer.
- [x] 1.3 `glab ci lint` báo cấu hình hợp lệ.

## 2. Tài liệu

- [x] 2.1 README, mục deploy: push không deploy, muốn deploy thì chạy pipeline trên `main`.
- [x] 2.2 `direct-owner-cutover`: task 14.4, 15.4 và quyết định 13 đổi thành "push rồi chạy pipeline trên `main`".
