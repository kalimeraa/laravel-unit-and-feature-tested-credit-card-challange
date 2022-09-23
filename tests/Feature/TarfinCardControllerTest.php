<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;
use Laravel\Passport\Passport;

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
        // 1. Arrange 🏗
        // TODO:

        // 2. Act 🏋🏻‍
        // TODO:

        // 3. Assert ✅
        // TODO:
    }

    /**
     * @test
     */
    public function a_customer_can_see_a_tarfin_card(): void
    {
        // 1. Arrange 🏗
        // TODO:

        // 2. Act 🏋🏻‍
        // TODO:

        // 3. Assert ✅
        // TODO:
    }

    /**
     * @test
     */
    public function a_customer_can_not_see_a_tarfin_card_of_another_customer(): void
    {
        // 1. Arrange 🏗
        // TODO:

        // 2. Act 🏋🏻‍
        // TODO:

        // 3. Assert ✅
        // TODO:
    }

    /**
     * @test
     */
    public function a_customer_can_list_tarfin_cards(): void
    {
        // 1. Arrange 🏗
        // TODO:

        // 2. Act 🏋🏻‍
        // TODO:

        // 3. Assert ✅
        // TODO:
    }

    /**
     * @test
     */
    public function a_customer_can_activate_the_tarfin_card(): void
    {
        // 1. Arrange 🏗
        // TODO:

        // 2. Act 🏋🏻‍
        // TODO:

        // 3. Assert ✅
        // TODO:
    }

    /**
     * @test
     */
    public function a_customer_can_deactivate_the_tarfin_card(): void
    {
        // 1. Arrange 🏗
        // TODO:

        // 2. Act 🏋🏻‍
        // TODO:

        // 3. Assert ✅
        // TODO:
    }

    /**
     * @test
     */
    public function a_customer_can_delete_a_tarfin_card(): void
    {
        // 1. Arrange 🏗
        // TODO:

        // 2. Act 🏋🏻‍
        // TODO:

        // 3. Assert ✅
        // TODO:
    }

    // THE MORE TESTS THE MORE POINTS 🏆
}
