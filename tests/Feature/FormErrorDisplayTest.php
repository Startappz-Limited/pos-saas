<?php

use App\Enums\Limit;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\PlanLimitExceeded;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

/**
 * Errors reach the person with the right status and are shown on the page:
 * business rules as messages (422 on JSON), validation with its field errors,
 * refusals and missing records with their own codes, unexpected failures as a
 * logged 500 without their internals; expired forms and honeypot catches go
 * back to the form; error pages are the app's own.
 */
class FailureProbeController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            match ($request->input('kind')) {
                'rule' => throw new BusinessRuleException('Return cannot be approved in its current status.'),
                'validation' => throw ValidationException::withMessages(['quantity' => ['The quantity must be at least 1.']]),
                'forbidden' => throw new AuthorizationException('This action is unauthorized.'),
                'missing' => throw (new ModelNotFoundException)->setModel(PurchaseOrder::class),
                'plan' => throw new PlanLimitExceeded(Limit::Shops, 1),
                default => throw new RuntimeException('SQLSTATE[42S22]: Column not found: secret_column'),
            };
        } catch (Exception $e) {
            return $this->failed($e, 'Failed to do it:');
        }
    }
}

beforeEach(function () {
    Route::middleware('web')->post('/__probe', FailureProbeController::class);
    Route::middleware('web')->post('/__expired', fn () => throw new TokenMismatchException);
    Route::middleware('web')->get('/__forbidden', fn () => abort(403, 'Only owners can see billing.'));
    Route::middleware('web')->get('/__crash', fn () => throw new RuntimeException('SQLSTATE secret details'));
    $this->user = User::factory()->create();
});

it('answers each kind of failure with its own status on JSON', function (string $kind, int $status) {
    $this->actingAs($this->user)->postJson('/__probe', ['kind' => $kind])->assertStatus($status);
})->with([
    'business rule' => ['rule', 422],
    'validation' => ['validation', 422],
    'not allowed' => ['forbidden', 403],
    'not found' => ['missing', 404],
    'plan limit (renders itself)' => ['plan', 402],
    'unexpected' => ['crash', 500],
]);

it('shows a business rule\'s message, and keeps validation\'s field errors', function () {
    $this->actingAs($this->user)->postJson('/__probe', ['kind' => 'rule'])
        ->assertJsonPath('message', 'Failed to do it: Return cannot be approved in its current status.');

    $this->actingAs($this->user)->postJson('/__probe', ['kind' => 'validation'])
        ->assertJsonValidationErrors(['quantity' => 'The quantity must be at least 1.']);

    $this->actingAs($this->user)->from('/sales/create')->post('/__probe', ['kind' => 'rule'])
        ->assertRedirect('/sales/create')
        ->assertSessionHas('error', 'Failed to do it: Return cannot be approved in its current status.');

    $this->actingAs($this->user)->from('/sales/create')->post('/__probe', ['kind' => 'validation'])
        ->assertRedirect('/sales/create')->assertSessionHasErrors('quantity');
});

it('reports an unexpected failure and never shows its internals', function () {
    Exceptions::fake();

    $json = $this->actingAs($this->user)->postJson('/__probe', ['kind' => 'crash'])->assertStatus(500);
    $web = $this->actingAs($this->user)->from('/sales/create')->post('/__probe', ['kind' => 'crash'])->assertRedirect('/sales/create');

    expect($json->json('message'))->not->toContain('SQLSTATE')->toContain('nothing was saved')
        ->and(session('error'))->not->toContain('SQLSTATE');
    Exceptions::assertReported(RuntimeException::class);
});

it('refuses a business rule through a real endpoint with 422 and its message', function () {
    $order = app(TenantContext::class)->run(app(TenantContext::class)->business(), fn () => PurchaseOrder::factory()->create());
    $owner = User::factory()->create();
    $owner->assignRole(ownerRole());
    Sanctum::actingAs($owner, ['*']);

    $this->postJson(route('api.purchase-orders.mark-ordered', $order))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only approved purchase orders can be marked as ordered.');
});

it('sends an expired form back to it with a message and what was typed', function () {
    $this->actingAs($this->user)->from('/customers/create')->post('/__expired', ['name' => 'Wanjiku', 'password' => 'secret'])
        ->assertRedirect('/customers/create')
        ->assertSessionHas('error')
        ->assertSessionHasInput('name', 'Wanjiku')
        ->assertSessionMissing('_old_input.password');
});

it('sends a honeypot catch back to the form with a message, not a blank page', function () {
    $this->from('/register')->post('/register', [
        'name' => 'Too Fast', 'business_name' => 'Quick Shop', 'email' => 'fast@example.test',
        'password' => 'Str0ng-P0S-Pass!', 'password_confirmation' => 'Str0ng-P0S-Pass!', 'my_name' => 'bot',
    ])->assertRedirect('/register')->assertSessionHas('error');

    $this->assertGuest();
    expect(User::where('email', 'fast@example.test')->exists())->toBeFalse();
    $this->get('/register')->assertSee('sent too quickly');
});

it('shows the app\'s own error pages, with the reason given and no internals', function () {
    $this->actingAs($this->user)->get('/__forbidden')->assertForbidden()
        ->assertSee('Not allowed')->assertSee('Only owners can see billing.');
    $this->actingAs($this->user)->get('/no-such-page')->assertNotFound()->assertSee('Page not found');

    config(['app.debug' => false]);
    $this->actingAs($this->user)->get('/__crash')->assertStatus(500)
        ->assertSee('Something went wrong')->assertDontSee('SQLSTATE');
});

it('shows sign-in errors and messages on the guest pages', function () {
    $this->from('/login')->post('/login', ['email' => $this->user->email, 'password' => 'wrong-password'])
        ->assertRedirect('/login');

    $this->get('/login')->assertSee('Please fix the following errors')->assertSee('responseErrorText');
});

describe('logs', function () {
    beforeEach(fn () => Log::spy());

    it('logs an unexpected failure as an error, with who and where', function () {
        $this->actingAs($this->user)->postJson('/__probe', ['kind' => 'crash']);

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'secret_column')
                && ($context['user_id'] ?? null) === $this->user->id
                && ($context['route_uri'] ?? null) === '__probe'
                && ($context['method'] ?? null) === 'POST'
                && array_key_exists('business_id', $context)
                && ($context['exception'] ?? null) instanceof RuntimeException;
        })->once();
    });

    it('logs a business rule refusal as a notice, not an error', function () {
        $this->actingAs($this->user)->postJson('/__probe', ['kind' => 'rule']);

        Log::shouldHaveReceived('notice')->withArgs(fn (string $message, array $context): bool => $message === 'Request refused: BusinessRuleException'
            && $context['reason'] === 'Return cannot be approved in its current status.'
            && $context['user_id'] === $this->user->id)->once();
        Log::shouldNotHaveReceived('error');
    });

    it('logs a refusal that renders itself (plan limit) as a notice', function () {
        $this->actingAs($this->user)->postJson('/__probe', ['kind' => 'plan'])->assertStatus(402);

        Log::shouldHaveReceived('notice')->withArgs(fn (string $message): bool => $message === 'Request refused: PlanLimitExceeded')->once();
        Log::shouldNotHaveReceived('error');
    });

    it('logs expired forms and honeypot catches, without what was typed', function () {
        $this->actingAs($this->user)->from('/customers/create')->post('/__expired', ['name' => 'Wanjiku', 'password' => 'secret']);
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => str_contains($message, '419')
            && ! str_contains(json_encode($context), 'secret') && ! str_contains(json_encode($context), 'Wanjiku'))->once();

        auth()->logout();
        $this->from('/register')->post('/register', ['name' => 'Bot', 'business_name' => 'X', 'email' => 'bot@example.test',
            'password' => 'Str0ng-P0S-Pass!', 'password_confirmation' => 'Str0ng-P0S-Pass!', 'my_name' => 'bot']);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Honeypot')
            && ($context['route_uri'] ?? null) === 'register' && ! str_contains(json_encode($context), 'Str0ng'))->once();
    });

    it('logs routes by their pattern, never a path carrying a token', function () {
        Route::middleware('web')->post('/__probe/{token}', FailureProbeController::class);

        $this->actingAs($this->user)->postJson('/__probe/super-secret-token', ['kind' => 'crash']);

        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => ($context['route_uri'] ?? null) === '__probe/{token}'
            && ! str_contains(json_encode(array_diff_key($context, ['exception' => 1])), 'super-secret-token'))->once();
    });
});
