<?php

namespace Tests\Unit;

use App\Rules\PersonName;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InputRulesTest extends TestCase
{
    public function test_person_names_allow_real_name_punctuation_and_reject_numbers(): void
    {
        foreach (["María Dela Cruz", "Anne-Marie O'Neil", 'Jose P. Rizal', 'Ma. Niño'] as $name) {
            $this->assertTrue(Validator::make(['name' => $name], ['name' => [new PersonName]])->passes(), $name);
        }

        foreach (['Juan123', 'Guest #1', '09171234567', '<b>Juan</b>'] as $name) {
            $this->assertFalse(Validator::make(['name' => $name], ['name' => [new PersonName]])->passes(), $name);
        }
    }

    public function test_philippine_mobile_number_is_exactly_eleven_digits_beginning_with_zero_nine(): void
    {
        $rule = new PhilippineMobileNumber;

        $this->assertTrue(Validator::make(['phone' => '09171234567'], ['phone' => [$rule]])->passes());

        foreach (['9171234567', '0917123456', '091712345678', '+639171234567', '0917ABC4567', '08171234567'] as $phone) {
            $this->assertFalse(Validator::make(['phone' => $phone], ['phone' => [$rule]])->passes(), $phone);
        }
    }

    public function test_only_safe_visual_separators_are_removed_during_phone_normalization(): void
    {
        $this->assertSame('09171234567', PhilippineMobileNumber::normalize('0917 123-4567'));
        $this->assertSame('0917ABC4567', PhilippineMobileNumber::normalize('0917ABC4567'));
    }

    public function test_nationality_style_values_allow_letters_but_not_numbers(): void
    {
        $rule = new PersonName;

        foreach (['Filipino', 'Filipino-American', 'I-Kiribati'] as $nationality) {
            $this->assertTrue(Validator::make(['nationality' => $nationality], ['nationality' => [$rule]])->passes());
        }

        $this->assertFalse(Validator::make(['nationality' => 'Filipino123'], ['nationality' => [$rule]])->passes());
    }
}
