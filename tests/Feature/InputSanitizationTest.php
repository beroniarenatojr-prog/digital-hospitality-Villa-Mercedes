<?php

namespace Tests\Feature;

use App\Http\Middleware\SanitizeInput;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class InputSanitizationTest extends TestCase
{
    public function test_plain_text_form_input_is_sanitized_recursively(): void
    {
        $request = Request::create('/submit', 'POST', [
            'name' => "<b>Jane</b>\0 Guest",
            'message' => "First line\r\n<script>alert('x')</script>Second line\x07",
            'companions' => [
                ['name' => '<em>John</em> Guest'],
            ],
        ]);

        (new SanitizeInput)->handle($request, fn () => new Response('ok'));

        $this->assertSame('Jane Guest', $request->input('name'));
        $this->assertSame("First line\nalert('x')Second line", $request->input('message'));
        $this->assertSame('John Guest', $request->input('companions.0.name'));
    }

    public function test_credentials_and_framework_tokens_are_not_modified(): void
    {
        $values = [
            '_token' => '<csrf>\0',
            'current_password' => '<old>\0',
            'password' => '<new>\0',
            'password_confirmation' => '<new>\0',
            'token' => '<signed>\0',
            'otp' => '<123456>\0',
        ];
        $request = Request::create('/submit', 'POST', $values);

        (new SanitizeInput)->handle($request, fn () => new Response('ok'));

        $this->assertSame($values, $request->request->all());
    }

    public function test_query_string_filters_are_sanitized_too(): void
    {
        $request = Request::create('/search?search=%3Cb%3EVilla%3C%2Fb%3E%00', 'GET');

        (new SanitizeInput)->handle($request, fn () => new Response('ok'));

        $this->assertSame('Villa', $request->query('search'));
    }
}
