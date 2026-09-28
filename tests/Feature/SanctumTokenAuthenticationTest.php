<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SanctumTokenAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('area_id')->nullable();
            $table->string('password');
            $table->string('remember_token', 100)->nullable();
            $table->string('role');
            $table->string('profile_image')->nullable();
            $table->string('status');
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });

        Schema::create('areas', function ($table) {
            $table->id();
        });

        Schema::create('personal_access_tokens', function ($table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_valid_token_authenticates_with_one_joined_token_and_user_query(): void
    {
        $user = $this->createUser('Administrator');
        $plainTextToken = $this->createToken($user, 'valid-secret');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        $this->assertNotNull($accessToken);
        $this->assertTrue($accessToken->relationLoaded('tokenable'));
        $this->assertSame($user->id, $accessToken->tokenable->id);
        $this->assertSame(1, count(DB::getQueryLog()));
        $this->assertStringContainsString(
            'left join "users"',
            strtolower(DB::getQueryLog()[0]['query'])
        );

        $authenticatedUser = $this->authenticate($plainTextToken);

        $this->assertNotNull($authenticatedUser);
        $this->assertSame($user->id, $authenticatedUser->id);
        $this->assertSame('Administrator', $authenticatedUser->role);
        $this->assertSame($accessToken->id, $authenticatedUser->currentAccessToken()->id);
    }

    public function test_invalid_token_secret_is_rejected(): void
    {
        $user = $this->createUser('Manager');
        $plainTextToken = $this->createToken($user, 'correct-secret');
        [$id] = explode('|', $plainTextToken, 2);

        $this->assertNull($this->authenticate($id.'|incorrect-secret'));
    }

    public function test_expired_token_is_rejected_by_sanctum_guard(): void
    {
        $user = $this->createUser('Manager');
        $plainTextToken = $this->createToken(
            $user,
            'expired-secret',
            now()->subMinute()
        );

        $this->assertNull($this->authenticate($plainTextToken));
    }

    public function test_deleted_token_is_rejected_immediately(): void
    {
        $user = $this->createUser('Manager');
        $plainTextToken = $this->createToken($user, 'revoked-secret');
        PersonalAccessToken::findToken($plainTextToken)->delete();

        $this->assertNull($this->authenticate($plainTextToken));
    }

    public function test_missing_user_tokenable_is_rejected_without_lazy_loading(): void
    {
        $plainTextToken = $this->createToken(
            null,
            'missing-user-secret',
            null,
            User::class,
            999999
        );

        $this->assertNull($this->authenticate($plainTextToken));
    }

    public function test_unsupported_tokenable_type_falls_back_and_is_rejected(): void
    {
        DB::table('areas')->insert(['id' => 1]);

        $plainTextToken = $this->createToken(
            null,
            'unsupported-type-secret',
            null,
            'App\\Models\\Area',
            1
        );

        $this->assertNull($this->authenticate($plainTextToken));
    }

    public function test_authenticated_dashboard_response_and_manager_role_are_preserved(): void
    {
        $user = $this->createUser('Manager');
        $plainTextToken = $this->createToken($user, 'dashboard-secret');
        $dashboardData = [
            'statistics' => ['totalUsers' => 1],
            'comparisons' => [],
        ];

        $service = Mockery::mock(DashboardService::class);
        $service->shouldReceive('getDashboardData')
            ->once()
            ->andReturn($dashboardData);
        $this->app->instance(DashboardService::class, $service);

        $response = $this->getJson('/api/dashboard', [
            'Authorization' => 'Bearer '.$plainTextToken,
        ]);

        $response->assertOk()->assertExactJson([
            'success' => true,
            'message' => 'Dashboard data fetched successfully.',
            'data' => $dashboardData,
        ]);
        $this->assertSame('Manager', $this->authenticate($plainTextToken)->role);
    }

    private function createUser(string $role): User
    {
        return User::forceCreate([
            'name' => 'Test User',
            'email' => strtolower($role).'-'.uniqid().'@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    private function createToken(
        ?User $user,
        string $secret,
        $expiresAt = null,
        ?string $tokenableType = null,
        ?int $tokenableId = null
    ): string {
        $token = new PersonalAccessToken();
        $token->forceFill([
            'tokenable_type' => $tokenableType ?? User::class,
            'tokenable_id' => $tokenableId ?? $user->id,
            'name' => 'test-token',
            'token' => hash('sha256', $secret),
            'abilities' => ['*'],
            'expires_at' => $expiresAt,
        ])->save();

        return $token->id.'|'.$secret;
    }

    private function authenticate(string $plainTextToken): ?User
    {
        $request = Request::create('/api/dashboard', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$plainTextToken,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $guard = Auth::guard('sanctum');
        $guard->setRequest($request);

        return $guard->user();
    }
}