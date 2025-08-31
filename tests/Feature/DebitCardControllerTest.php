<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // Create debit cards for current user
        $debitCards = DebitCard::factory(3)->create(['user_id' => $this->user->id]);
        
        // Create debit cards for other user (should not be visible)
        $otherUser = User::factory()->create();
        DebitCard::factory(2)->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200)
                ->assertJsonCount(3, 'data')
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'number',
                            'type',
                            'expiration_date',
                            'is_active'
                        ]
                    ]
                ]);

        // Assert only current user's cards are returned
        foreach ($debitCards as $card) {
            $response->assertJsonFragment(['id' => $card->id]);
        }
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // Create debit cards for other user
        $otherUser = User::factory()->create();
        $otherUserCards = DebitCard::factory(2)->create(['user_id' => $otherUser->id]);
        
        // Create debit card for current user
        $myCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data');

        // Assert only current user's card is returned
        $response->assertJsonFragment(['id' => $myCard->id]);
        
        // Assert other user's cards are not returned
        foreach ($otherUserCards as $card) {
            $response->assertJsonMissing(['id' => $card->id]);
        }
    }

    public function testCustomerCanCreateADebitCard()
    {
        $debitCardData = [
            'number' => '1234567890123456',
            'type' => 'visa',
            'expiration_date' => '2025-12-31'
        ];

        $response = $this->postJson('/api/debit-cards', $debitCardData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'number',
                        'type', 
                        'expiration_date',
                        'is_active'
                    ]
                ]);

        // Assert database has the new debit card
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'number' => '1234567890123456',
            'type' => 'visa',
            'expiration_date' => '2025-12-31',
            'disabled_at' => null
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'number',
                        'type',
                        'expiration_date',
                        'is_active'
                    ]
                ])
                ->assertJsonFragment([
                    'id' => $debitCard->id,
                    'number' => $debitCard->number,
                    'type' => $debitCard->type
                ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // Create debit card for other user
        $otherUser = User::factory()->create();
        $otherUserCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/debit-cards/{$otherUserCard->id}");

        $response->assertStatus(403); // Forbidden by policy
    }

    public function testCustomerCanActivateADebitCard()
    {
        // Create disabled debit card
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => now()
        ]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'disabled_at' => null
        ]);

        $response->assertStatus(200);

        // Assert card is activated
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'disabled_at' => null
        ]);

        $debitCard->refresh();
        $this->assertTrue($debitCard->is_active);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => null
        ]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'disabled_at' => now()->toDateTimeString()
        ]);

        $response->assertStatus(200);

        // Assert card is deactivated
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id
        ]);

        $debitCard->refresh();
        $this->assertFalse($debitCard->is_active);
        $this->assertNotNull($debitCard->disabled_at);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        // Test with invalid number (too short)
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'number' => '123'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['number']);

        // Test with invalid type
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'type' => 'invalid_type'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['type']);

        // Test with invalid expiration date (past date)
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'expiration_date' => '2020-01-01'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['expiration_date']);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(200);

        // Assert debit card is soft deleted
        $this->assertSoftDeleted('debit_cards', ['id' => $debitCard->id]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        
        // Create transaction for this debit card
        DebitCardTransaction::factory()->create(['debit_card_id' => $debitCard->id]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(422); // Cannot delete card with transactions

        // Assert debit card is not deleted
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'deleted_at' => null
        ]);
    }

    public function testCustomerCannotCreateDebitCardWithInvalidData()
    {
        // Test missing required fields
        $response = $this->postJson('/api/debit-cards', []);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['number', 'type', 'expiration_date']);

        // Test invalid number format
        $response = $this->postJson('/api/debit-cards', [
            'number' => '123',
            'type' => 'visa',
            'expiration_date' => '2025-12-31'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['number']);

        // Test duplicate number
        $existingCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        $response = $this->postJson('/api/debit-cards', [
            'number' => $existingCard->number,
            'type' => 'mastercard',
            'expiration_date' => '2025-12-31'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['number']);
    }

    public function testCustomerCannotUpdateOtherCustomerDebitCard()
    {
        // Create debit card for other user
        $otherUser = User::factory()->create();
        $otherUserCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->putJson("/api/debit-cards/{$otherUserCard->id}", [
            'type' => 'mastercard'
        ]);

        $response->assertStatus(403); // Forbidden by policy
    }

    public function testCustomerCannotDeleteOtherCustomerDebitCard()
    {
        // Create debit card for other user
        $otherUser = User::factory()->create();
        $otherUserCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->deleteJson("/api/debit-cards/{$otherUserCard->id}");

        $response->assertStatus(403); // Forbidden by policy

        // Assert card is not deleted
        $this->assertDatabaseHas('debit_cards', [
            'id' => $otherUserCard->id,
            'deleted_at' => null
        ]);
    }
}