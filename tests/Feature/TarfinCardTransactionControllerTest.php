<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CurrencyType;
use App\Jobs\ProcessTarfinCardTransactionJob;
use App\Models\TarfinCard;
use App\Models\TarfinCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;

class TarfinCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase,WithFaker;

    /**
     * @test
     */
    public function a_customer_can_create_a_tarfin_card_transaction(): void
    {
        Bus::fake();
        Http::fake();
        Http::post('http://you-should-mock-this-job', [
            'tarfin_card_transaction_id' => 1,
        ]);

        $customer = User::factory()->create();
        Passport::actingAs($customer,['create']);
        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->active()->create();
        $payload = TarfinCardTransaction::factory()->make()->toArray();
        unset($payload['tarfin_card_id']);

        $response = $this->post($this->api . '/' . $tarfinCard->id . '/tarfin-card-transactions',$payload);
        
        $response->assertCreated()->assertJson(fn (AssertableJson $json) =>
        $json->has('data')->first(fn ($json) => 
                $json->where('amount', $payload['amount'])
                     ->where('currency_code', $payload['currency_code'])
       ));

       $payload['tarfin_card_id'] = $tarfinCard->id;

       $this->assertDatabaseHas('tarfin_card_transactions',$payload);

       Bus::assertDispatched(ProcessTarfinCardTransactionJob::class);

       Http::assertSent(function (Request $request) {
        return $request->url() == 'http://you-should-mock-this-job' &&
               $request['tarfin_card_transaction_id'] == 1;
       });
    }

    /**
     * @test
     */
    public function a_customer_can_not_create_a_tarfin_card_transaction_for_a_tarfin_card_of_another_customer(): void
    {
        $tarfinCard = TarfinCard::factory()->active()->create();
        $customer = User::factory()->create();
        Passport::actingAs($customer,['create']);
        $payload = TarfinCardTransaction::factory()->make()->toArray();
        unset($payload['tarfin_card_id']);

        $response = $this->post($this->api . '/' . $tarfinCard->id . '/tarfin-card-transactions',$payload);

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function a_customer_can_see_a_tarfin_card_transaction(): void
    {
        $customer = User::factory()->create();
        $tarfinCard = TarfinCard::factory()->active()->forCustomer($customer)->create();
        Passport::actingAs($customer,['view']);
        $tarfinCardTransaction = TarfinCardTransaction::factory()->forTarfinCard($tarfinCard)->create();

        $response = $this->get('api/tarfin-card-transactions/' . $tarfinCardTransaction->id);

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->has('data')->first(fn ($json) => 
                $json->where('amount', $tarfinCardTransaction->amount)
                     ->where('currency_code', $tarfinCardTransaction->currency_code->value)
        ));
    }

    /**
     * @test
     */
    public function a_customer_can_not_see_a_tarfin_card_transaction_for_a_tarfin_card_of_another_customer(): void
    {
        $customer = User::factory()->create();
        $tarfinCard = TarfinCard::factory()->active()->create();
        Passport::actingAs($customer,['view']);
        $tarfinCardTransaction = TarfinCardTransaction::factory()->forTarfinCard($tarfinCard)->create();

        $response = $this->get('api/tarfin-card-transactions/' . $tarfinCardTransaction->id);

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function a_customer_can_list_tarfin_card_transactions(): void
    {
        $customer = User::factory()->create();
        $tarfinCard = TarfinCard::factory()->active()->forCustomer($customer)->create();
        Passport::actingAs($customer,['view-any']);
        $tarfinCardTransactions = TarfinCardTransaction::factory()->forTarfinCard($tarfinCard)->count(2)->create();

        $response = $this->get($this->api . '/' . $tarfinCard->id . '/tarfin-card-transactions');
        
        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->has('data')
            ->has('data.0',fn ($json) => 
                $json->where('amount', $tarfinCardTransactions->first()->amount)
                ->where('currency_code', $tarfinCardTransactions->first()->currency_code->value)
            )->has('data.1',fn ($json) =>
                $json->where('amount', $tarfinCardTransactions[1]->amount)
                ->where('currency_code', $tarfinCardTransactions[1]->currency_code->value)
        ));
    }

    /**
     * @test
     */
    public function a_customer_can_not_list_tarfin_card_transactions_for_a_tarfin_card_of_another_customer(): void
    {
        $customer = User::factory()->create();
        $tarfinCard = TarfinCard::factory()->active()->create();
        Passport::actingAs($customer,['view-any']);
        TarfinCardTransaction::factory()->forTarfinCard($tarfinCard)->count(2)->create();

        $response = $this->get($this->api . '/' . $tarfinCard->id . '/tarfin-card-transactions');

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function amount_must_be_an_integer_when_create_a_tarfin_card_transaction()
    {
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['create']
        );

        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->active()->create();

        $response = $this->post(
            $this->api . '/' . $tarfinCard->id . '/tarfin-card-transactions',
            ['amount' => $this->faker->name(),'currency_code' => CurrencyType::TRY->value],
            ['Accept' => 'application/json']
        );

        $expectedJson = [
            "message" => "The amount must be an integer.",
            "errors" => [
                "amount" => [
                    "The amount must be an integer."
                ]
            ]
       ];
       
       $response->assertUnprocessable()->assertJson($expectedJson);
    }

    /**
     * @test
     */
    public function currency_code_must_be_an_string_and_valid_when_create_a_tarfin_card_transaction()
    {
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['create']
        );

        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->active()->create();

        $response = $this->post(
            $this->api . '/' . $tarfinCard->id . '/tarfin-card-transactions',
            ['amount' => rand(1,2),'currency_code' => rand(1,2)],
            ['Accept' => 'application/json']
        );

        $expectedJson = [
            "message" => "The currency code must be a string. (and 1 more error)",
            "errors" => [
                "currency_code" => [
                    "The currency code must be a string.",
                    "The selected currency code is invalid."
                ]
            ]
       ];
       
       $response->assertUnprocessable()->assertJson($expectedJson);
    }

    /**
     * @test
     */
    public function currency_code_and_amount_are_required_when_create_a_tarfin_card_transaction()
    {
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['create']
        );

        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->active()->create();

        $response = $this->post(
            $this->api . '/' . $tarfinCard->id . '/tarfin-card-transactions',
            [],
            ['Accept' => 'application/json']
        );

        $expectedJson = [
            "message" => "The amount field is required. (and 1 more error)",
            "errors" => [
                "amount" => [
                    "The amount field is required.",
                ],
                "currency_code" => [
                    "The currency code field is required."
                ]
            ]
       ];
       
       $response->assertUnprocessable()->assertJson($expectedJson);
    }
}
