# AGENTS.md

Project agent rules. Detailed conventions live in `.agents/rules/` — read `backend-architecture.md`, `frontend-architecture.md`, and `collaboration.md` before non-trivial work.

## Resolve from context, then decide (MUST)

When an advisor concern, ambiguity, or missing endpoint/prerequisite is flagged, FIRST automatically read the relevant context to resolve it: the change specs, design, report-traceability, existing code, tests, and project conventions. Reading context is a required step before any decision or halt.

After reading context, autonomously choose the best option and proceed (you MAY spawn worker sub-agents). Prefer the safest, most spec-consistent, boring option; cut over cleanly with no shims. Record the decision, the options considered, and the rationale so it can be reviewed.

Only halt and ask the user when context genuinely cannot resolve it — a missing external value (real credentials, business consent, money authority), or a true product tradeoff with no safe default. In that case present the concrete options (A / B / …) with tradeoffs and your recommendation.

Khi có cảnh báo (advisor concern), điểm chưa rõ, hoặc thiếu endpoint/tiền đề: TRƯỚC HẾT tự động đọc context liên quan để giải quyết — spec của change, design, report-traceability, code hiện có, test và quy ước dự án. Đọc context là bước BẮT BUỘC trước khi quyết định hay dừng.

Sau khi đọc context, tự chốt phương án tốt nhất và tiếp tục (ĐƯỢC PHÉP spawn sub-agent). Ưu tiên phương án an toàn nhất, bám spec nhất, đơn giản nhất; cutover sạch, không shim. Ghi lại quyết định, các phương án đã cân nhắc và lý do để có thể review.

Chỉ dừng và hỏi người dùng khi context thực sự không giải quyết được — thiếu giá trị bên ngoài (credential thật, sự đồng ý nghiệp vụ, quyền chuyển tiền), hoặc đánh đổi sản phẩm thật sự không có lựa chọn mặc định an toàn. Khi đó trình bày các phương án (A / B / …) kèm đánh đổi và khuyến nghị của bạn.
