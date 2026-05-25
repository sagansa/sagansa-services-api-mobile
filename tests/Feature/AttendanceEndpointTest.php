<?php

use App\Models\Store;
use App\Models\User;
use App\Models\Tenant;
use App\Models\PersonalAccessToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('can checkin with valid form data', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    
    // Create token using Laravel's Sanctum method
    $token = $user->createToken('test-token')->plainTextToken;

    $photo = UploadedFile::fake()->image('selfie.jpg');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'X-Tenant-ID' => $tenant->id,
        'Accept' => 'application/json',
    ])->post('/api/attendance/checkin', [
        'store_id' => $store->id,
        'photo' => $photo,
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'accuracy' => 10,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'attendance' => [
                'id',
                'user_id',
                'store_id',
                'check_in_at',
            ],
        ]);

    Storage::disk('public')->assertExists('attendances/' . $photo->hashName());
});

it('validates required fields for checkin', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    // Create token using Laravel's Sanctum method
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'X-Tenant-ID' => $tenant->id,
        'Accept' => 'application/json',
    ])->postJson('/api/attendance/checkin', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['store_id', 'photo', 'latitude', 'longitude']);
});

it('validates store belongs to user tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $otherStore = Store::factory()->create(); // Different tenant
    
    // Create token using Laravel's Sanctum method
    $token = $user->createToken('test-token')->plainTextToken;

    $photo = UploadedFile::fake()->image('selfie.jpg');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'X-Tenant-ID' => $tenant->id,
        'Accept' => 'application/json',
    ])->postJson('/api/attendance/checkin', [
        'store_id' => $otherStore->id,
        'photo' => $photo,
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'accuracy' => 10,
    ]);

    $response->assertStatus(403);
});