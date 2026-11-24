<?php

namespace Tests\Feature;

use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** DAILY_MESSAGE_LIMIT (config/conversation.php) read by ConversationService::dailyMessageLimit(). */
class DailyMessageLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_is_fifty_and_phpunit_locks_it(): void
    {
        $this->assertSame(50, ConversationService::DAILY_MESSAGE_LIMIT);
        $this->assertEquals(50, config('conversation.daily_message_limit'));
        $this->assertSame(50, ConversationService::dailyMessageLimit());
        $this->assertStringContainsString('<env name="DAILY_MESSAGE_LIMIT" value="50" force="true"/>', file_get_contents(base_path('phpunit.xml')));
    }

    public function test_value_comes_from_the_environment(): void
    {
        $saved = [$_SERVER['DAILY_MESSAGE_LIMIT'] ?? null, $_ENV['DAILY_MESSAGE_LIMIT'] ?? null];
        $_SERVER['DAILY_MESSAGE_LIMIT'] = $_ENV['DAILY_MESSAGE_LIMIT'] = '120';

        try {
            $config = require config_path('conversation.php');
        } finally {
            [$_SERVER['DAILY_MESSAGE_LIMIT'], $_ENV['DAILY_MESSAGE_LIMIT']] = $saved;
        }

        config(['conversation.daily_message_limit' => $config['daily_message_limit']]);
        $this->assertSame(120, ConversationService::dailyMessageLimit());
    }

    public function test_configured_limit_is_what_remains_for_a_new_browser(): void
    {
        config(['conversation.daily_message_limit' => '75']);
        RateLimiter::clear('ngobrol:day:127.0.0.1');

        $this->assertSame(75, app(ConversationService::class)->remainingMessagesToday());
    }

    /** @return array<string, array{mixed, int}> */
    public static function values(): array
    {
        return [
            'one' => ['1', 1],
            'upper bound' => ['1000', 1000],
            'integer' => [200, 200],
            'spaces' => [' 30 ', 30],
            'zero' => ['0', 50],
            'negative' => ['-5', 50],
            'above upper bound' => ['1001', 50],
            'huge' => ['99999999', 50],
            'decimal' => ['12.5', 50],
            'text' => ['banyak', 50],
            'empty' => ['', 50],
            'null' => [null, 50],
            'bool true' => [true, 50],
        ];
    }

    #[DataProvider('values')]
    public function test_invalid_values_fall_back_to_the_default(mixed $value, int $expected): void
    {
        config(['conversation.daily_message_limit' => $value]);

        $this->assertSame($expected, ConversationService::dailyMessageLimit());
    }

    public function test_env_examples_document_it(): void
    {
        $this->assertMatchesRegularExpression('/^DAILY_MESSAGE_LIMIT=50$/m', file_get_contents(base_path('.env.example')));
        $this->assertMatchesRegularExpression('/^DAILY_MESSAGE_LIMIT=50$/m', file_get_contents(base_path('.env.production.example')));
        $this->assertStringContainsString('`DAILY_MESSAGE_LIMIT`', file_get_contents(base_path('README.md')));
    }
}
