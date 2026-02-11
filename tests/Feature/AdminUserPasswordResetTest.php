<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('admin can reset user password from admin panel', function () {
    config(['app.admin_token' => 'test-admin-token']);

    $user = User::factory()->create([
        'password' => 'old-password',
    ]);

    $response = $this->post(
        route('admin.users.reset-password', [
            'user' => $user,
            'token' => 'test-admin-token',
        ]),
        [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ],
    );

    $response->assertRedirect(route('admin.users.show', [
        'user' => $user,
        'token' => 'test-admin-token',
    ]));
    $response->assertSessionHas('success', 'Пароль пользователя успешно сброшен');

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
    expect(Hash::check('old-password', $user->fresh()->password))->toBeFalse();
});

test('admin password reset requires valid admin token', function () {
    config(['app.admin_token' => 'test-admin-token']);

    $user = User::factory()->create();

    $response = $this->post(
        route('admin.users.reset-password', ['user' => $user]),
        [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ],
    );

    $response->assertForbidden();
});

test('admin password reset requires password confirmation', function () {
    config(['app.admin_token' => 'test-admin-token']);

    $user = User::factory()->create([
        'password' => 'old-password',
    ]);

    $response = $this
        ->from(route('admin.users.show', [
            'user' => $user,
            'token' => 'test-admin-token',
        ]))
        ->post(
            route('admin.users.reset-password', [
                'user' => $user,
                'token' => 'test-admin-token',
            ]),
            [
                'password' => 'new-password',
                'password_confirmation' => 'invalid-confirmation',
            ],
        );

    $response->assertRedirect(route('admin.users.show', [
        'user' => $user,
        'token' => 'test-admin-token',
    ]));
    $response->assertSessionHasErrors('password');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});
