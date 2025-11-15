<?php

namespace Tests\Feature;

use App\Models\User;
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
        // get /debit-cards

        // create some debit cards for this user
        $cards = \App\Models\DebitCard::factory()
            ->count(3)
            ->active()
            ->create(['user_id' => $this->user->id]);

        // create inactive debit cards for this user
        \App\Models\DebitCard::factory()
            ->expired()
            ->create(['user_id' => $this->user->id]);

        // create card for other user
        \App\Models\DebitCard::factory()->active()->create();

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);

        // return data only for this user and only active cards
        $response->assertJsonCount(3);

        foreach ($cards as $card) {
            $response->assertJsonFragment([
                'id' => $card->id,
                'type' => $card->type,
            ]);
        }
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards

        // create some debit cards for other user
        $otherUser = User::factory()->create();

        $otherCards = \App\Models\DebitCard::factory()
            ->count(2)
            ->active()
            ->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);

        // no data for other users
        foreach ($otherCards as $card) {
            $response->assertJsonMissing([
                'id' => $card->id,
            ]);
        }
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards

        // valid payload
        $payload = [
            'type' => 'visa',
            'number' => '4111111111111111',
            'expiry_date' => '2030-12-01',
        ];

        $response = $this->postJson('/api/debit-cards', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'type' => 'visa',
            ]);

        // database check
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'visa',
            // 'number' => '4111111111111111',
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}

        // create a debit card for this user
        $card = \App\Models\DebitCard::factory()
            ->active()
            ->create([
                'user_id' => $this->user->id,
            ]);

        $response = $this->getJson('/api/debit-cards/' . $card->id);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $card->id,
                'type' => $card->type,
                // 'number' => $card->number,
            ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}

        $otherUser = User::factory()->create();

        $card = \App\Models\DebitCard::factory()
            ->active()
            ->create([
                'user_id' => $otherUser->id,
            ]);

        $response = $this->getJson('/api/debit-cards/' . $card->id);

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}

        $card = \App\Models\DebitCard::factory()
            ->expired()
            ->create([
                'user_id' => $this->user->id,
            ]);

        $response = $this->putJson('/api/debit-cards/' . $card->id, [
            'is_active' => true,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $card->id,
            'disabled_at' => null,
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}

        $card = \App\Models\DebitCard::factory()
            ->active()
            ->create([
                'user_id' => $this->user->id,
            ]);

        $response = $this->putJson('/api/debit-cards/' . $card->id, [
            'is_active' => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('debit_cards', [
            'id' => $card->id,
            'disabled_at' => null,
        ]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $card->id,
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}

        $card = \App\Models\DebitCard::factory()
            ->active()
            ->create(['user_id' => $this->user->id]);

        $response = $this->putJson('/api/debit-cards/' . $card->id, [
            'is_active' => 'not-boolean',
        ]);

        $response->assertStatus(422);

        // ensure database unchanged
        $this->assertDatabaseHas('debit_cards', [
            'id' => $card->id,
            'disabled_at' => null,
        ]);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}

        $card = \App\Models\DebitCard::factory()
            ->active()
            ->create([
                'user_id' => $this->user->id,
            ]);

        $response = $this->deleteJson('/api/debit-cards/' . $card->id);

        $response->assertStatus(204);

        // Soft delete check
        $this->assertSoftDeleted('debit_cards', [
            'id' => $card->id,
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}

        $card = \App\Models\DebitCard::factory()
            ->active()
            ->create([
                'user_id' => $this->user->id,
            ]);

        // create transaction tied to this card
        \App\Models\DebitCardTransaction::factory()
            ->create([
                'debit_card_id' => $card->id,
            ]);

        $response = $this->deleteJson('/api/debit-cards/' . $card->id);

        $response->assertStatus(403);

        // ensure not deleted
        $this->assertDatabaseHas('debit_cards', [
            'id' => $card->id,
            'deleted_at' => null,
        ]);
    }

    // Extra bonus for extra tests :)
}
