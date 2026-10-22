<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Mark Google-created users without exposing their Google ID.
     */
    private function addGoogleIndicator($user)
    {
        if ($user instanceof User) {
            $isGoogleUser = !empty($user->google_id);

            $user->setAttribute('is_google_user', $isGoogleUser);
            $user->makeHidden(['google_id']);

            return $user;
        }

        if (is_array($user)) {
            if (
                array_key_exists('id', $user) &&
                array_key_exists('email', $user)
            ) {
                $user['is_google_user'] = !empty($user['google_id']);
                unset($user['google_id']);

                return $user;
            }

            return $this->markGoogleUsers($user);
        }

        if (is_object($user) && isset($user->email)) {
            $googleId = $user->google_id ?? null;

            $user->is_google_user = !empty($googleId);

            if (method_exists($user, 'makeHidden')) {
                $user->makeHidden(['google_id']);
            } else {
                unset($user->google_id);
            }

            return $user;
        }

        return $user;
    }

    /**
     * Transform paginated, collection or nested user responses.
     */
    private function markGoogleUsers($result)
    {
        if ($result instanceof AbstractPaginator) {
            $result->getCollection()->transform(
                fn ($user) => $this->addGoogleIndicator($user)
            );

            return $result;
        }

        if ($result instanceof Collection) {
            return $result->map(
                fn ($user) => $this->addGoogleIndicator($user)
            );
        }

        if (is_array($result)) {
            if (
                array_key_exists('id', $result) &&
                array_key_exists('email', $result)
            ) {
                return $this->addGoogleIndicator($result);
            }

            foreach ($result as $key => $value) {
                if (
                    $value instanceof AbstractPaginator ||
                    $value instanceof Collection
                ) {
                    $result[$key] = $this->markGoogleUsers($value);
                } elseif (is_array($value)) {
                    $result[$key] = $this->markGoogleUsers($value);
                } elseif ($value instanceof User) {
                    $result[$key] = $this->addGoogleIndicator($value);
                }
            }

            return $result;
        }

        return $result;
    }

    /**
     * Get all users.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            $this->markGoogleUsers(
                $this->userService->getUsers()
            )
        );
    }

    /**
     * Get users for dropdowns.
     */
    public function options(): JsonResponse
    {
        return response()->json(
            $this->userService->getUserOptions()
        );
    }

    /**
     * Get a single user.
     */
    public function show(User $user): JsonResponse
    {
        $user->setAttribute(
            'is_google_user',
            !empty($user->google_id)
        );

        $user->makeHidden(['google_id']);

        return response()->json(
            $this->userService->findUserById($user)
        );
    }

    /**
     * Create a user through management.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image');
        }

        return response()->json(
            $this->userService->createUser($data),
            201
        );
    }

    /**
     * Update a user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user
    ): JsonResponse {
        $data = $request->validated();

        // Google-linked accounts must remain Public User even if a client
        // attempts to submit a different role.
        if (!empty($user->google_id)) {
            $data['role'] = 'Public User';
        }

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image');
        }

        return response()->json(
            $this->userService->updateUser($user, $data)
        );
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): JsonResponse
    {
        return response()->json(
            $this->userService->deleteUser($user)
        );
    }
}
