<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminUsersRequest;
use App\Http\Requests\Admin\SetAccountLockRequest;
use App\Models\User;
use App\Services\Admin\AccountLockService;
use Illuminate\Http\JsonResponse;

/** BR-23: platform admins review accounts and lock or unlock them with a reason. */
class AdminUserController extends Controller
{
    public function __construct(
        private readonly AccountLockService $locks,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function index(ListAdminUsersRequest $request): JsonResponse
    {
        return response()->json($this->users->paginateForAdmin(
            $request->validated('search'),
            $request->validated('status'),
            (int) $request->validated('per_page', 20),
        ));
    }

    public function lock(SetAccountLockRequest $request, User $user): JsonResponse
    {
        $locked = $this->locks->lock($request->user(), $user, $request->validated('reason'), $request->ip());

        return response()->json(['message' => 'Đã khóa tài khoản.', 'user' => $locked->only(['id', 'email', 'status'])]);
    }

    public function unlock(SetAccountLockRequest $request, User $user): JsonResponse
    {
        $unlocked = $this->locks->unlock($request->user(), $user, $request->validated('reason'), $request->ip());

        return response()->json(['message' => 'Đã mở khóa tài khoản.', 'user' => $unlocked->only(['id', 'email', 'status'])]);
    }
}
