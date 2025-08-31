<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // Create transactions for current user's debit card
        $transactions = DebitCardTransaction::factory(3)->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        // Create another debit card for current user with transactions
        $anotherCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        $moreTransactions = DebitCardTransaction::factory(2)->create([
            'debit_card_id' => $anotherCard->id
        ]);

        $response = $this->getJson('/api/debit-card-transactions');

        $response->assertStatus(200)
                ->assertJsonCount(5, 'data') // Total 5 transactions for current user
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'amount',
                            'currency_code',
                            'description',
                            'created_at',
                            'debit_card_id'
                        ]
                    ]
                ]);

        // Assert all user's transactions are returned
        $allTransactions = $transactions->merge($moreTransactions);
        foreach ($allTransactions as $transaction) {
            $response->assertJsonFragment(['id' => $transaction->id]);
        }
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // Create transaction for current user's debit card
        $myTransaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        // Create debit card for other user with transactions
        $otherUser = User::factory()->create();
        $otherUserCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $otherUserTransactions = DebitCardTransaction::factory(3)->create([
            'debit_card_id' => $otherUserCard->id
        ]);

        $response = $this->getJson('/api/debit-card-transactions');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data'); // Only current user's transaction

        // Assert only current user's transaction is returned
        $response->assertJsonFragment(['id' => $myTransaction->id]);

        // Assert other user's transactions are not returned
        foreach ($otherUserTransactions as $transaction) {
            $response->assertJsonMissing(['id' => $transaction->id]);
        }
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        $transactionData = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100.50,
            'currency_code' => 'USD',
            'description' => 'Test transaction'
        ];

        $response = $this->postJson('/api/debit-card-transactions', $transactionData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'amount',
                        'currency_code',
                        'description',
                        'created_at',
                        'debit_card_id'
                    ]
                ])
                ->assertJsonFragment([
                    'amount' => 100.50,
                    'currency_code' => 'USD',
                    'description' => 'Test transaction',
                    'debit_card_id' => $this->debitCard->id
                ]);

        // Assert database has the new transaction
        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100.50,
            'currency_code' => 'USD',
            'description' => 'Test transaction'
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // Create debit card for other user
        $otherUser = User::factory()->create();
        $otherUserCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $transactionData = [
            'debit_card_id' => $otherUserCard->id,
            'amount' => 100.00,
            'currency_code' => 'USD',
            'description' => 'Unauthorized transaction'
        ];

        $response = $this->postJson('/api/debit-card-transactions', $transactionData);

        $response->assertStatus(403); // Forbidden by policy

        // Assert transaction was not created
        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $otherUserCard->id,
            'amount' => 100.00
        ]);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'amount',
                        'currency_code',
                        'description',
                        'created_at',
                        'debit_card_id'
                    ]
                ])
                ->assertJsonFragment([
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'currency_code' => $transaction->currency_code,
                    'description' => $transaction->description
                ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // Create debit card for other user
        $otherUser = User::factory()->create();
        $otherUserCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $otherUserTransaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherUserCard->id
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$otherUserTransaction->id}");

        $response->assertStatus(403); // Forbidden by policy
    }

    // Extra bonus tests :)
    
    public function testCustomerCannotCreateDebitCardTransactionWithInvalidData()
    {
        // Test missing required fields
        $response = $this->postJson('/api/debit-card-transactions', []);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['debit_card_id', 'amount', 'currency_code']);

        // Test invalid amount (negative)
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => -50.00,
            'currency_code' => 'USD',
            'description' => 'Invalid amount'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);

        // Test invalid currency code
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100.00,
            'currency_code' => 'INVALID',
            'description' => 'Invalid currency'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['currency_code']);

        // Test non-existent debit card
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => 99999,
            'amount' => 100.00,
            'currency_code' => 'USD',
            'description' => 'Non-existent card'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['debit_card_id']);
    }

    public function testCustomerCannotCreateTransactionOnDisabledDebitCard()
    {
        // Disable the debit card
        $this->debitCard->update(['disabled_at' => now()]);

        $transactionData = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100.00,
            'currency_code' => 'USD',
            'description' => 'Transaction on disabled card'
        ];

        $response = $this->postJson('/api/debit-card-transactions', $transactionData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['debit_card_id']);

        // Assert transaction was not created
        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100.00
        ]);
    }

    public function testTransactionListCanBeFilteredByDebitCard()
    {
        // Create another debit card for same user
        $anotherCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        
        // Create transactions for first card
        $firstCardTransactions = DebitCardTransaction::factory(2)->create([
            'debit_card_id' => $this->debitCard->id
        ]);
        
        // Create transactions for second card
        DebitCardTransaction::factory(3)->create([
            'debit_card_id' => $anotherCard->id
        ]);

        // Filter by first debit card
        $response = $this->getJson("/api/debit-card-transactions?debit_card_id={$this->debitCard->id}");

        $response->assertStatus(200)
                ->assertJsonCount(2, 'data');

        // Assert only first card's transactions are returned
        foreach ($firstCardTransactions as $transaction) {
            $response->assertJsonFragment(['id' => $transaction->id]);
        }
    }

    public function testTransactionAmountValidation()
    {
        // Test zero amount
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 0,
            'currency_code' => 'USD',
            'description' => 'Zero amount'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);

        // Test very large amount
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 999999999.99,
            'currency_code' => 'USD', 
            'description' => 'Large amount'
        ]);
        // This should pass if no maximum limit is set, or fail if there is one
        $this->assertTrue(in_array($response->status(), [201, 422]));
    }

    public function testTransactionDescriptionValidation()
    {
        // Test very long description
        $longDescription = str_repeat('a', 256); // Assuming max 255 chars
        
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 50.00,
            'currency_code' => 'USD',
            'description' => $longDescription
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['description']);

        // Test empty description (should be allowed)
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 50.00,
            'currency_code' => 'USD',
            'description' => ''
        ]);
        $response->assertStatus(201);
    }

    public function testTransactionTimestampAccuracy()
    {
        $beforeCreation = now();
        
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 25.00,
            'currency_code' => 'USD',
            'description' => 'Timestamp test'
        ]);

        $afterCreation = now();

        $response->assertStatus(201);
        
        $transactionId = $response->json('data.id');
        $transaction = DebitCardTransaction::find($transactionId);
        
        $this->assertTrue($transaction->created_at->between($beforeCreation, $afterCreation));
    }
}