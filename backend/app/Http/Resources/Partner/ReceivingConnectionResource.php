<?php

namespace App\Http\Resources\Partner;

use App\Models\ReceivingConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivingConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ReceivingConnection $connection */
        $connection = $this->resource;

        return [
            'id' => $connection->id,
            'routing_id' => $connection->routing_id,
            'provider' => $connection->provider,
            'bank_code' => $connection->bank_code,
            'account_name' => $connection->accountName(),
            'account_number_masked' => $connection->maskedAccountNumber(),
            'status' => $connection->status,
            'is_selected' => $connection->isSelectedForVenue(),
            'credential_version' => (int) $connection->credential_version,
            'credential_configured' => $connection->credential_version > 0,
            'query_api_configured' => $connection->api_token_version > 0,
            'query_api_version' => (int) $connection->api_token_version,
            'provider_account_id' => $connection->provider_account_id,
            'verified_at' => $connection->verified_at?->toIso8601String(),
            'effective_from' => $connection->effective_from?->toIso8601String(),
            'effective_until' => $connection->effective_until?->toIso8601String(),
            'webhook_url' => url('/api/webhooks/sepay/connections/'.$connection->routing_id),
            'onboarding' => [
                'product' => 'SePay Webhooks',
                'event' => 'Inbound transfers only',
                'format' => 'JSON',
                'authentication' => 'HMAC-SHA256',
                'signature_header' => 'X-SePay-Signature: sha256=<hex>',
                'timestamp_header' => 'X-SePay-Timestamp',
                'signed_content' => 'timestamp.raw_body',
                'success_response' => ['status' => 200, 'body' => ['success' => true]],
                'instructions_vi' => [
                    'Trong SePay, liên kết tài khoản ngân hàng mà tổ chức được quyền sử dụng.',
                    'Tạo webhook nhận tiền vào, định dạng JSON, xác thực HMAC-SHA256 và dán URL ở trên.',
                    'Cấu hình trường code lấy nội dung AC-prefixed bằng mẫu AC[A-Z0-9]+; khách phải giữ nguyên mã chuyển khoản do hệ thống cấp.',
                    'Lưu secret vào Aura Court; secret chỉ được lưu mã hóa và sẽ không hiển thị lại.',
                    'Gửi một giao dịch đến đúng tài khoản để xác minh webhook. Không nhập mật khẩu ngân hàng.',
                    'Chỉ dùng thu tiền trực tuyến sau khi trạng thái kết nối là verified.',
                ],
                'instructions_en' => [
                    'Link a bank account the organization is authorized to use in SePay.',
                    'Create an inbound JSON webhook with HMAC-SHA256 authentication and use the URL above.',
                    'Configure the code field to extract AC-prefixed transfer content with AC[A-Z0-9]+; customers must keep the issued transfer code unchanged.',
                    'Store the secret in Aura Court; it is encrypted and will not be shown again.',
                    'Send one inbound event to the configured account to verify delivery. Never enter bank login credentials.',
                    'Enable online collection only after the connection status is verified.',
                ],
                'optional_transaction_query' => [
                    'base_url' => rtrim((string) config('services.sepay.api_v2_base_url', 'https://userapi.sepay.vn/v2'), '/'),
                    'authentication' => 'Bearer API token (server-side only)',
                    'instructions_vi' => [
                        'Tùy chọn: nhập SePay API token và ID tài khoản ngân hàng trong cấu hình bảo mật để đồng bộ giao dịch khi webhook bị lỡ.',
                        'API token chỉ được lưu mã hóa, không bao giờ trả lại trong phản hồi.',
                    ],
                    'instructions_en' => [
                        'Optional: store a SePay API token and bank account ID for server-side transaction recovery when a webhook is missed.',
                        'The API token is encrypted at rest and is never returned by the API.',
                    ],
                ],
            ],
            'created_at' => $connection->created_at?->toIso8601String(),
        ];
    }
}
