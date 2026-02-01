<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Patient;
use App\Models\Diary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DiaryInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_diary_invite()
    {
        $user = User::factory()->create([
            'type' => 'client',
        ]);

        $patient = Patient::create([
            'owner_id' => $user->id,
            'creator_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birth_date' => '1950-01-01',
            'gender' => 'male',
            'mobility' => 'walking',
        ]);

        $diary = Diary::create(['patient_id' => $patient->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/invitations/diary', [
                'patient_id' => $patient->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['invite_url']);

        $this->assertDatabaseHas('invitations', [
            'patient_id' => $patient->id,
            'type' => Invitation::TYPE_DIARY_ACCESS,
            'inviter_id' => $user->id,
        ]);
    }

    public function test_accept_diary_invite_creates_new_user_and_grants_access()
    {
        $owner = User::factory()->create(['type' => 'client']);
        $patient = Patient::create(['owner_id' => $owner->id, 'creator_id' => $owner->id, 'first_name' => 'John', 'last_name' => 'Doe', 'gender' => 'male', 'birth_date' => '1950-01-01', 'mobility' => 'walking']);
        $diary = Diary::create(['patient_id' => $patient->id]);

        $invitation = Invitation::create([
            'inviter_id' => $owner->id,
            'token' => Invitation::generateToken(),
            'type' => Invitation::TYPE_DIARY_ACCESS,
            'patient_id' => $patient->id,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
            'phone' => '79001112233',
            'password' => 'password',
            'password_confirmation' => 'password',
            'first_name' => 'New',
            'last_name' => 'Caregiver',
            'type' => 'private_caregiver'
        ]);

        $response->assertStatus(200);

        $newUser = User::where('phone', '79001112233')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('private_caregiver', $newUser->type->value);

        // Check Access
        $this->assertTrue($diary->hasAccess($newUser, 'full'));
    }

    public function test_accept_diary_invite_allows_pansionat_or_agency_type()
    {
        $owner = User::factory()->create(['type' => 'client']);
        $patient = Patient::create([
            'owner_id' => $owner->id,
            'creator_id' => $owner->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1950-01-01',
            'mobility' => 'walking',
        ]);
        $diary = Diary::create(['patient_id' => $patient->id]);

        $invitation = Invitation::create([
            'inviter_id' => $owner->id,
            'token' => Invitation::generateToken(),
            'type' => Invitation::TYPE_DIARY_ACCESS,
            'patient_id' => $patient->id,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
            'phone' => '79001113344',
            'password' => 'password',
            'password_confirmation' => 'password',
            'first_name' => 'Org',
            'last_name' => 'User',
            'type' => 'pansionat',
        ]);

        $response->assertStatus(200);

        $newUser = User::where('phone', '79001113344')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('organization', $newUser->type->value);
        $this->assertTrue($diary->hasAccess($newUser, 'full'));
    }

    public function test_accept_diary_invite_links_organization()
    {
        $owner = User::factory()->create(['type' => 'client']);
        $patient = Patient::create(['owner_id' => $owner->id, 'creator_id' => $owner->id, 'first_name' => 'John', 'last_name' => 'Doe', 'gender' => 'male', 'birth_date' => '1950-01-01', 'mobility' => 'walking']);
        $diary = Diary::create(['patient_id' => $patient->id]);

        $invitation = Invitation::create([
            'inviter_id' => $owner->id,
            'token' => Invitation::generateToken(),
            'type' => Invitation::TYPE_DIARY_ACCESS,
            'patient_id' => $patient->id,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        // Create an existing organization user
        $orgUser = User::factory()->create([
            'type' => 'organization',
            'phone' => '79008889900',
            'password' => Hash::make('password'),
        ]);

        // Assume user belongs to an org
        $org = \App\Models\Organization::create([
            'owner_id' => $orgUser->id,
            'name' => 'Test Agency',
            'type' => 'agency' // Assuming 'agency' is valid
        ]);
        $orgUser->update(['organization_id' => $org->id]);

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
            'phone' => '79008889900',
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        // Assert patient linked to organization
        $patient->refresh();
        $this->assertEquals($org->id, $patient->organization_id);
    }
}
