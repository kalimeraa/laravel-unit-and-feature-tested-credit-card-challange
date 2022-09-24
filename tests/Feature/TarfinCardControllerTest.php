<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Resources\TarfinCardResource;
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

    private string $api = 'api/tarfin-cards';
    
    /**
     * @test
     */
    public function a_customer_can_create_a_tarfin_card(): void
    {
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['create']
        );

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

        $expectedJson = ['data' => json_decode((new TarfinCardResource($tarfinCard))->toJson(),true)];

        $response = $this->get($this->api . '/' . $tarfinCard->id);
        
        $response->assertOk()->assertJson($expectedJson);
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

        $tarfinCards = TarfinCard::factory()->forCustomer($customer)->active()->count(5)->create();
        $collection = json_decode(TarfinCardResource::collection($tarfinCards)->toJson(),true);
        $expectedJson = ['data' => $collection];
        $response = $this->get($this->api);
        
        $response->assertOk()->assertJson($expectedJson);
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

        $response->assertOk()->assertJson(['data' => $payload]);
    }

    /**
     * @test
     */
    public function a_customer_can_deactivate_the_tarfin_card(): void
    {
        $customer = User::factory()->create();
        Passport::actingAs(
            $customer,
            ['update']
        );

        $tarfinCard = TarfinCard::factory()->forCustomer($customer)->active()->create();
        $payload = ['is_active' => false];

        $response = $this->put($this->api . '/' . $tarfinCard->id,$payload);

        $response->assertOk()->assertJson(['data' => $payload]);
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

        Passport::actingAs(
            $customer,
            ['delete']
        );

        $response = $this->delete($this->api . '/' . $tarfinCard->id);
        $response->json();
        $expectedJson = ['data' => json_decode((new TarfinCardResource($tarfinCard->refresh()))->toJson(),true)];

        $response->assertOk()->assertJson($expectedJson);
        Notification::assertSentTo(
            [$customer], TarfinCardDeletedNotification::class
        );

        Http::assertSent(function (Request $request) use($tarfinCard) {
            return $request->url() == 'http://you-should-mock-this-mail-service' &&
                   $request['tarfin_card_id'] == $tarfinCard->id &&
                   $request['message'] == "Your Tarfin Card #{$tarfinCard->number} is deleted.";
        });

        $this->assertSoftDeleted('tarfin_cards',['id' => $tarfinCard->id]);
    }
}
