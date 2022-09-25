<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TarfinCard;
use App\Models\User;
use App\Notifications\TarfinCardDeletedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Client\Request;

class TarfinCardControllerTest extends TestCase
{
    use RefreshDatabase,WithFaker;
    
    /**
     * @test
     */
    public function a_customer_can_create_a_tarfin_card(): void
    {
        $customer = User::factory()->create();
        Passport::actingAs($customer,['create']);

        $creditCardType = $this->faker->creditCardType();
        $response = $this->post($this->api,['type' => $creditCardType]);

        $response->assertCreated()->assertJson(fn (AssertableJson $json) =>
        $json->has('data')->first(fn ($json) => 
                $json->where('id', 1)
                     ->where('type', $creditCardType)
                     ->where('is_active', true)
                     ->where('expiration_date', fn(string $date) => Carbon::now()->lt($date))
                     ->where('number',function(int $number){
                        if($number >= 1000000000000000 && $number <= 9999999999999999) {
                            return true;
                        }
                        return false;
                     })
       ));
        
       $this->assertDatabaseHas('tarfin_cards',['user_id' => $customer->id,'type' => $creditCardType]);
    }

    /**
     * @test
     */
    public function a_customer_can_not_create_an_invalid_tarfin_card(): void
    {
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['create']
        );

        $response = $this->post($this->api,['type' => $this->faker->text()],['Accept' => 'application/json']);
        $expectedJson = [
            "message" => "The selected type is invalid.",
            "errors" => [
                "type" => [
                    "The selected type is invalid."
                ]
            ]
       ];
       
       $response->assertUnprocessable()->assertJson($expectedJson);
    }

    /**
     * @test
     */
    public function a_customer_can_see_a_tarfin_card(): void
    {
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['view']
        );

        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->create();

        $response = $this->get($this->api . '/' . $tarfinCard->id);

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->has('data')->first(fn ($json) => 
                $json->where('id', $tarfinCard->id)
                     ->where('type', $tarfinCard->type)
                     ->where('is_active', $tarfinCard->is_active)
                     ->where('expiration_date', fn(string $date) => $tarfinCard->expiration_date->eq($date))
                     ->where('number',$tarfinCard->number)
       ));
    }

    /**
     * @test
     */
    public function a_customer_can_not_see_a_tarfin_card_of_another_customer(): void
    {
        $tarfinCard = TarfinCard::factory()->create();
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['view']
        );
        
        $response = $this->get($this->api . '/' . $tarfinCard->id);
        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function a_customer_can_list_tarfin_cards(): void
    {
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['view-any']
        );

        $tarfinCards = TarfinCard::factory()->forCustomer($customer)->active()->count(2)->create();
    
        $response = $this->get($this->api);
        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->has('data')
            ->has('data.0',fn ($json) => 
                $json->where('id', $tarfinCards->first()->id)
                    ->where('type', $tarfinCards->first()->type)
                    ->where('is_active', $tarfinCards->first()->is_active)
                    ->where('expiration_date', fn(string $date) => $tarfinCards->first()->expiration_date->eq($date))
                    ->where('number',$tarfinCards->first()->number)
            )->has('data.1',fn ($json) =>
                $json->where('id', $tarfinCards[1]->id)
                    ->where('type', $tarfinCards[1]->type)
                    ->where('is_active', $tarfinCards[1]->is_active)
                    ->where('expiration_date', fn(string $date) => $tarfinCards[1]->expiration_date->eq($date))
                    ->where('number',$tarfinCards[1]->number)
        ));
    }

    /**
     * @test
     */
    public function a_customer_can_activate_the_tarfin_card(): void
    {
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['update']
        );

        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->deactive()->create();
        $payload = ['is_active' => true];

        $response = $this->put($this->api . '/' . $tarfinCard->id,$payload);

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
            $json->has('data')->first(fn ($json) => 
                $json->where('id', $tarfinCard->id)
                     ->where('type', $tarfinCard->type)
                     ->where('is_active', true)
                     ->where('expiration_date', fn(string $date) => $tarfinCard->expiration_date->eq($date))
                     ->where('number',$tarfinCard->number)
       ));
    }

    /**
     * @test
     */
    public function a_customer_can_deactivate_the_tarfin_card(): void
    {
        $customer = User::factory()->create();
        Passport::actingAs($customer,['update']);
        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->active()->create();
        $payload = ['is_active' => false];

        $response = $this->put($this->api . '/' . $tarfinCard->id,$payload);

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
            $json->has('data')->first(fn ($json) => 
                $json->where('id', $tarfinCard->id)
                    ->where('type', $tarfinCard->type)
                    ->where('is_active', false)
                    ->where('expiration_date', fn(string $date) => $tarfinCard->expiration_date->eq($date))
                    ->where('number',$tarfinCard->number)
       ));
    }

    /**
     * @test
     */
    public function a_customer_can_not_update_a_tarfin_card_of_another_customer()
    {
        $tarfinCard = TarfinCard::factory()->create();
        $customer = User::factory()->create();
        Passport::actingAs($customer,['update']);
        
        $response = $this->put($this->api . '/' . $tarfinCard->id);
        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function it_must_be_shown_is_active_parameter_should_be_boolean_for_updating_a_tarfin_card()
    {
        $customer = User::factory()->create();
        Passport::actingAs($customer,['update']);
        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->create();
        $response = $this->put($this->api.'/' . $tarfinCard->id,['is_active' => $this->faker->name],['accept' => 'application/json']);

        $expectedJson = [
            "message" => "The is active field must be true or false.",
            "errors" => [
                "is_active" => [
                    "The is active field must be true or false."
                ]
            ]
       ];

       $response->assertUnprocessable()->assertJson($expectedJson);
    }

    /**
     * @test
     */
    public function it_must_be_shown_is_active_parameter_required_for_updating_a_tarfin_card()
    {
        $customer = User::factory()->create();
        Passport::actingAs($customer,['update']);

        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->create();
        $response = $this->put($this->api.'/' . $tarfinCard->id,[],['accept' => 'application/json']);
        $expectedJson = [
            "message" => "The is active field is required.",
            "errors" => [
                "is_active" => [
                    "The is active field is required."
                ]
            ]
       ];

       $response->assertUnprocessable()->assertJson($expectedJson);
    }

    /**
     * @test
     */
    public function a_customer_can_delete_a_tarfin_card(): void
    {
        Notification::fake();
        Http::fake();

        $customer = User::factory()->create();
        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->create();

        Http::post('http://you-should-mock-this-mail-service', [
            'tarfin_card_id' => $tarfinCard->id,
            'message'        => "Your Tarfin Card #{$tarfinCard->number} is deleted.",
        ]);

        Passport::actingAs($customer,['delete']);

        $response = $this->delete($this->api . '/' . $tarfinCard->id);

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
            $json->has('data')->first(fn ($json) => 
                $json->where('id', $tarfinCard->id)
                    ->where('type', $tarfinCard->type)
                    ->where('is_active', $tarfinCard->is_active)
                    ->where('expiration_date', fn(string $date) => $tarfinCard->expiration_date->eq($date))
                    ->where('number',$tarfinCard->number)
       ));

        Notification::assertSentTo([$customer], TarfinCardDeletedNotification::class);

        Http::assertSent(function (Request $request) use($tarfinCard) {
            return $request->url() == 'http://you-should-mock-this-mail-service' &&
                   $request['tarfin_card_id'] == $tarfinCard->id &&
                   $request['message'] == "Your Tarfin Card #{$tarfinCard->number} is deleted.";
        });

        $this->assertSoftDeleted('tarfin_cards',['id' => $tarfinCard->id]);
    }

    /**
     * @test
     */
    public function a_customer_can_not_delete_a_tarfin_card_of_another_customer()
    {
        $tarfinCard = TarfinCard::factory()->create();
        $customer = User::factory()->create();
        Passport::actingAs($customer,['delete']);
        
        $response = $this->delete($this->api . '/' . $tarfinCard->id);
        $response->assertForbidden();
    }
}
