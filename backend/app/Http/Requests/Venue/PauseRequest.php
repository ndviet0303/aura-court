<?php

namespace App\Http\Requests\Venue;

use App\Models\Court;
use App\Models\OperationalStop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * BR-35: the one request for pausing and closing a venue or a court (D5). A reason of 10–1000 characters after
 * trimming, an optional start no earlier than now (60 seconds of clock skew allowed; omitted means now) and, for a
 * pause only, an optional expected end after the start. Only a court pause names its kind; a venue pause is
 * always a business pause and a closure has no kind.
 */
class PauseRequest extends FormRequest
{
    private const START_TOLERANCE_SECONDS = 60;

    public function authorize(): bool
    {
        $court = $this->route('court');
        if (! $court instanceof Court) {
            return LifecycleCommandRequest::ownsRouteVenue($this);
        }

        $user = $this->user();
        // BR-34: assigned staff record only a maintenance pause; every other court stop is the owner's.
        $staffMayAct = ! $this->isClosure() && $this->input('kind') === OperationalStop::KIND_MAINTENANCE;

        return $user !== null && ($staffMayAct
            ? $user->canOperateVenue((int) $court->venue_id)
            : $user->canManageVenue((int) $court->venue_id));
    }

    /** The global TrimStrings middleware has already trimmed the reason. */
    public function rules(): array
    {
        $closing = $this->isClosure();

        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'acknowledge_affected' => ['sometimes', 'boolean'],
            'expected_end_at' => $closing ? ['prohibited'] : ['nullable', 'date'],
            'kind' => match (true) {
                $closing => ['prohibited'],
                $this->route('court') instanceof Court => ['required', Rule::in([OperationalStop::KIND_MAINTENANCE, OperationalStop::KIND_BUSINESS])],
                default => ['nullable', Rule::in([OperationalStop::KIND_BUSINESS])],
            },
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do.',
            'reason' => 'Lý do phải có từ 10 đến 1000 ký tự.',
            'starts_at' => 'Thời điểm bắt đầu không hợp lệ.',
            'expected_end_at.prohibited' => 'Ngừng kinh doanh không có thời điểm dự kiến kết thúc.',
            'expected_end_at' => 'Thời điểm dự kiến kết thúc không hợp lệ.',
            'kind.prohibited' => 'Ngừng kinh doanh không có loại tạm ngừng.',
            'kind' => 'Loại tạm ngừng không hợp lệ: sân nhận bảo trì hoặc tạm ngừng kinh doanh, cơ sở chỉ nhận tạm ngừng kinh doanh.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['starts_at', 'expected_end_at'])) {
                return;
            }
            $now = CarbonImmutable::now();
            $start = $this->filled('starts_at') ? CarbonImmutable::parse($this->input('starts_at')) : $now;
            if ($start->lt($now->subSeconds(self::START_TOLERANCE_SECONDS))) {
                $validator->errors()->add('starts_at', 'Thời điểm bắt đầu không được ở quá khứ.');

                return;
            }
            if ($this->filled('expected_end_at') && CarbonImmutable::parse($this->input('expected_end_at'))->lte($start->max($now))) {
                $validator->errors()->add('expected_end_at', 'Thời điểm dự kiến kết thúc phải sau thời điểm bắt đầu.');
            }
        });
    }

    public function isClosure(): bool
    {
        return in_array($this->route()?->getActionMethod(), ['close', 'previewClose'], true);
    }

    /** The stop kind this request records. */
    public function stopKind(): string
    {
        return $this->isClosure()
            ? OperationalStop::KIND_CLOSURE
            : ($this->validated('kind') ?? OperationalStop::KIND_BUSINESS);
    }
}
