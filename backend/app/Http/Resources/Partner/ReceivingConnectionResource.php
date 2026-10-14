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
            'webhook_url' => url('/api/v1/webhooks/sepay/connections/'.$connection->routing_id),
            'onboarding' => [
                'product' => 'SePay Webhooks',
                'event' => 'In_only',
                'format' => 'JSON',
                'authentication' => 'Api_Key',
                'authorization_header' => 'Authorization: Apikey <key>',
                'payment_code' => [
                    'prefix' => 'AC',
                    'suffix_min_length' => 18,
                    'suffix_max_length' => 18,
                    'suffix_type' => 'NumberAndLetter',
                ],
                'success_response' => ['status' => 200, 'body' => ['success' => true]],
                'instructions_vi' => [
                    'Trong SePay, liên kết tài khoản ngân hàng mà tổ chức được quyền sử dụng.',
                    'Trong Cấu hình công ty, thêm cấu trúc mã thanh toán: tiền tố AC, hậu tố dài 18–18 ký tự, kiểu NumberAndLetter. Khách phải giữ nguyên mã chuyển khoản do hệ thống cấp.',
                    'Tạo webhook: sự kiện In_only (chỉ tiền vào), dán URL ở trên, kiểu xác thực Api_Key.',
                    'Tạo một API key ít nhất 32 ký tự chỉ gồm chữ, số, _ hoặc - (ví dụ openssl rand -hex 32), dán vào ô API Key của webhook SePay và lưu cùng key đó vào Aura Court. SePay sẽ gửi header Authorization: Apikey <key>.',
                    'Key chỉ được lưu mã hóa và sẽ không hiển thị lại.',
                    'Gửi một giao dịch đến đúng tài khoản để xác minh webhook. Không nhập mật khẩu ngân hàng.',
                    'Chỉ dùng thu tiền trực tuyến sau khi trạng thái kết nối là verified.',
                ],
                'instructions_en' => [
                    'Link a bank account the organization is authorized to use in SePay.',
                    'In Company configuration, add a payment code format: prefix AC, suffix length 18–18, type NumberAndLetter. Customers must keep the issued transfer code unchanged.',
                    'Create a webhook: event In_only (incoming money only), paste the URL above, authentication type Api_Key.',
                    'Generate an API key of at least 32 characters using only letters, digits, _ or - (for example openssl rand -hex 32), paste it into the SePay webhook API Key field and save the same key in Aura Court. SePay sends it as the header Authorization: Apikey <key>.',
                    'The key is encrypted at rest and will not be shown again.',
                    'Send one inbound transfer to the configured account to verify delivery. Never enter bank login credentials.',
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
