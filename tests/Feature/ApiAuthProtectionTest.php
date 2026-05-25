<?php

use Tests\TestCase;

it('requires auth for attendance list', function () {
    $response = $this->getJson('/api/attendance');
    $response->assertStatus(401);
})->group('auth');

it('requires auth for attendance checkin', function () {
    $response = $this->postJson('/api/attendance/checkin', []);
    $response->assertStatus(401);
})->group('auth');

it('requires auth for attendance checkout', function () {
    $response = $this->postJson('/api/attendance/checkout', []);
    $response->assertStatus(401);
})->group('auth');

it('requires auth for printers index', function () {
    $response = $this->getJson('/api/printers');
    $response->assertStatus(401);
})->group('auth');

it('requires auth for creating orders', function () {
    $response = $this->postJson('/api/orders', []);
    $response->assertStatus(401);
})->group('auth');

it('validates login payload', function () {
    $response = $this->postJson('/api/auth/login', []);
    $response->assertStatus(422);
})->group('auth');