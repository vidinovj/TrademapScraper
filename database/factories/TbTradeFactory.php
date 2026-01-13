<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TbTrade>
 */
class TbTradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negara' => 'Indonesia',
            'kode_hs' => $this->faker->numerify('##'),
            'label' => $this->faker->sentence(3),
            'tahun' => 2024,
            'jumlah' => $this->faker->randomFloat(2, 1000, 1000000),
            'satuan' => 'USD thousands',
            'sumber_data' => 'Trademap',
            'scraped_at' => now(),
        ];
    }
}