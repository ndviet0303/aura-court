# Aura Court Frontend

Vue 3 + TypeScript SPA cho Aura Court, build bằng Vite và deploy trên Cloudflare Workers.

## Lệnh phát triển

```bash
npm ci
npm run dev
```

Các lệnh kiểm tra:

```bash
npm run type-check
npm run test:unit -- --run
npm run build
npm run lint
```

## Biến môi trường

`VITE_API_URL` là cấu hình build-time, dùng làm fallback cho origin API:

```env
VITE_API_URL=https://court-api.ziet.dev
```

Worker runtime đọc thêm biến public `PUBLIC_API_URL` từ Cloudflare Workers và trả qua `/runtime-config.json`. Không đưa secret vào frontend.

## Deploy Cloudflare Workers

`wrangler.jsonc` dùng entrypoint `worker.ts`, binding `ASSETS` và phục vụ SPA từ `dist`.

Cloudflare build settings:

- Root directory: `frontend`
- Build command: `npm ci && npm run build`
- Deploy command: `npx wrangler deploy`

Deploy thủ công:

```bash
npm ci
npm run build
npx wrangler deploy
```

Sau khi Worker chạy, thêm biến Plaintext trong **Settings → Variables and secrets**:

```env
PUBLIC_API_URL=https://court-api.ziet.dev
```

Không thêm `APP_KEY`, mật khẩu SMTP, SePay secret hoặc thông tin database vào Worker frontend.
