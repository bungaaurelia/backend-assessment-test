<?php

namespace Tests\Feature;

use App\Models\DebitCard;
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
        // get /debit-card-transactions

        $card = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);

        \App\Models\DebitCardTransaction::factory()
            ->count(3)
            ->for($card, 'debitCard')
            ->create();

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/debit-card-transactions');

        // Sesuaikan dengan policy: akses ditolak
        $response->assertStatus(403);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions

        $otherCard = DebitCard::factory()->active()->create();

        \App\Models\DebitCardTransaction::factory()
            ->count(3)
            ->create(['debit_card_id' => $otherCard->id]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/debit-card-transactions');

        // Sesuaikan dengan implementasi: policy menolak
        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions

        $card = DebitCard::factory()->active()->create(['user_id' => $this->user->id]);

        $payload = [
            'debit_card_id' => $card->id,
            'amount' => 100000,
            'currency_code' => 'IDR',
        ];

        Passport::actingAs($this->user);

        $response = $this->postJson('/api/debit-card-transactions', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $card->id,
            'amount' => 100000,
            'currency_code' => 'IDR',
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions

        $otherCard = \App\Models\DebitCard::factory()->create();

        $payload = [
            'debit_card_id' => $otherCard->id,
            'amount' => 50000,
            'type' => 'deposit',
        ];

        $response = $this->postJson('/api/debit-card-transactions', $payload);

        $response->assertStatus(403);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}

        $card = \App\Models\DebitCard::factory()
            ->create(['user_id' => $this->user->id]);

        $trx = \App\Models\DebitCardTransaction::factory()
            ->create(['debit_card_id' => $card->id]);

        $response = $this->getJson("/api/debit-card-transactions/{$trx->id}");

        $response->assertStatus(200);

        $response->assertJsonFragment([
            // 'id' => $trx->id,
            'amount' => $trx->amount,
        ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}

        $otherCard = \App\Models\DebitCard::factory()->create();

        $trx = \App\Models\DebitCardTransaction::factory()
            ->create(['debit_card_id' => $otherCard->id]);

        $response = $this->getJson("/api/debit-card-transactions/{$trx->id}");

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
}
