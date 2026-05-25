<?php

use App\Models\Store;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can complete full attendance checkin flow with multipart form data', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    // Create token using Laravel's Sanctum method
    $token = $user->createToken('test-token')->plainTextToken;

    $photo = UploadedFile::fake()->image('selfie.jpg');

    // Test the exact same format as the React Native app
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'X-Tenant-ID' => $tenant->id,
        'Accept' => 'application/json',
    ])->post('/api/attendance/checkin', [
        'store_id' => $store->id,
        'photo' => $photo,
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'accuracy' => 10.5,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'attendance' => [
                'id',
                'user_id',
                'store_id',
                'check_in_at',
                'latitude',
                'longitude',
                'accuracy',
                'photo_path',
            ],
        ]);

    // Verify the attendance was created with correct data
    $this->assertDatabaseHas('attendances', [
        'user_id' => $user->id,
        'store_id' => $store->id,
        'tenant_id' => $tenant->id,
        'type' => 'regular',
        'status' => 'present',
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'accuracy' => 10.5,
    ]);

    // Verify photo was stored
    $photoPath = $response->json('attendance.photo_path');
    $this->assertNotNull($photoPath);
    $this->assertStringContainsString('attendances/', $photoPath);
});
