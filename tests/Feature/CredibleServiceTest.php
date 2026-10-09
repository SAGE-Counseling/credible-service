<?php

namespace Sage\Credible\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Sage\Credible\Services\CredibleService;
use Sage\Credible\Tests\TestCase;

/**
 * All data here is synthetic. Credible is never contacted: the SOAP client is a
 * stub, HTTP is faked, and Redis is mocked.
 */
class CredibleServiceTest extends TestCase
{
    private const DATASET = '<NewDataSet>'
        . '<Table><client_id>101</client_id><first_name>Test</first_name></Table>'
        . '<Table><client_id>102</client_id><first_name>Sample</first_name></Table>'
        . '</NewDataSet>';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_get_returns_rows_with_numeric_values_cast_and_clears_backoff(): void
    {
        Redis::shouldReceive('ttl')->once()->andReturn(-2);
        Redis::shouldReceive('del')->once();

        $service = new CredibleService('fake-connection', $this->soapStub((object) [
            'schema' => '',
            'any' => self::DATASET,
        ]));

        $rows = $service->get('ExportName');

        $this->assertSame([
            ['client_id' => 101, 'first_name' => 'Test'],
            ['client_id' => 102, 'first_name' => 'Sample'],
        ], $rows);
        $this->assertSame([], Storage::allFiles('temp'));
    }

    public function test_yield_rows_streams_html_encoded_rows_and_removes_the_temp_file(): void
    {
        $service = new CredibleService('fake-connection', $this->soapStub((object) [
            'any' => htmlspecialchars(self::DATASET),
        ]));

        $rows = iterator_to_array($service->yieldRows('ExportName'), false);

        $this->assertSame([
            ['client_id' => '101', 'first_name' => 'Test'],
            ['client_id' => '102', 'first_name' => 'Sample'],
        ], $rows);
        $this->assertSame([], Storage::allFiles('temp'));
    }

    public function test_post_sends_the_file_to_the_import_endpoint(): void
    {
        Redis::shouldReceive('del')->once();
        Http::fake(['*' => Http::response('OK', 200)]);

        $service = new CredibleService('fake-connection');

        $this->assertSame('OK', $service->post('ZW5jb2RlZA==', 'https://credible.test'));

        Http::assertSent(fn (Request $request) =>
            $request->url() === 'https://credible.test/reports/Importservice.asmx/Import'
            && $request['connection'] === 'fake-connection'
            && $request['encodedfile'] === 'ZW5jb2RlZA=='
        );
    }

    public function test_post_returns_null_on_a_failed_response(): void
    {
        Redis::shouldReceive('del')->once();
        Http::fake(['*' => Http::response('error', 500)]);

        $service = new CredibleService('fake-connection');

        $this->assertNull($service->post('ZW5jb2RlZA==', 'https://credible.test'));
    }

    public function test_escalate_backoff_uses_the_current_step(): void
    {
        $key = 'backoff:api:' . CredibleService::class;

        Redis::shouldReceive('get')->once()->with("{$key}:step")->andReturn('2');
        Redis::shouldReceive('setex')->once()->with($key, 120, 1);
        Redis::shouldReceive('set')->once()->with("{$key}:step", 3);

        $this->assertSame(120, (new CredibleService('fake-connection'))->escalateBackoff());
    }

    public function test_escalate_backoff_stays_on_the_last_step_once_exhausted(): void
    {
        $key = 'backoff:api:' . CredibleService::class;

        Redis::shouldReceive('get')->once()->andReturn('99');
        Redis::shouldReceive('setex')->once()->with($key, 1500, 1);
        Redis::shouldReceive('set')->once()->with("{$key}:step", 10);

        $this->assertSame(1500, (new CredibleService('fake-connection'))->escalateBackoff());
    }

    private function soapStub(object $result): object
    {
        return new class ($result) {
            public function __construct(private object $result)
            {
            }

            public function ExportDataSet(array $params): object
            {
                return (object) ['ExportDataSetResult' => $this->result];
            }
        };
    }
}
