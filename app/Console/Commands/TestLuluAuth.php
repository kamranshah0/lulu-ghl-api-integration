<?php

namespace App\Console\Commands;

use App\Services\LuluApiService;
use App\Exceptions\LuluApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestLuluAuth extends Command
{
    protected $signature = 'lulu:test 
                            {--fresh : Force a fresh token fetch} 
                            {--full : Attempt a dry-run print job submission}';

    protected $description = 'Advanced Lulu API Diagnostic Tool';

    public function handle(LuluApiService $luluApi)
    {
        $this->info('🚀 Starting Lulu API Premium Diagnostic...');
        $this->line('--------------------------------------------------');

        // 1. Auth Check
        if ($this->option('fresh')) {
            $this->warn('🧹 Clearing token cache...');
            $useSandbox = config('services.lulu.use_sandbox', true);
            $cacheKey = 'lulu_access_token_' . ($useSandbox ? 'sandbox' : 'production');
            Cache::forget($cacheKey);
        }

        try {
            $this->comment('📡 Testing Authentication...');
            $token = $luluApi->getAccessToken();
            $this->info('✅ Auth Success!');
            $this->line('   Env: ' . (config('services.lulu.use_sandbox') ? 'Sandbox' : 'Production'));
            $this->line('   Client ID: ' . config('services.lulu.client_key'));
        } catch (LuluApiException $e) {
            $this->error('❌ Auth Failed: ' . $e->getMessage());
            $this->renderTroubleshooting();
            return 1;
        }

        // 2. Full Integration Check (Optional)
        if ($this->option('full')) {
            $this->line('');
            $this->info('📦 Starting Full Flight Test (Mock order)...');
            $this->comment('   Target: Beverly Hills Demo Address');

            $mockAddress = [
                'name'         => 'Diagnostic Test',
                'street1'      => '30 N Gould St',
                'city'         => 'Sheridan',
                'state_code'   => 'WY',
                'postcode'     => '82801',
                'country_code' => 'US',
                'phone_number' => '1234567890'
            ];

            try {
                $response = $luluApi->createPrintJob($mockAddress, 'DIAG-' . uniqid());
                
                $this->info('✅ Integration Success! Lulu accepted the mock order.');
                $this->info('   Lulu Job ID: ' . ($response['id'] ?? 'Unknown'));
                $this->info('   Lulu Status: ' . ($response['status']['name'] ?? 'CREATED'));

            } catch (LuluApiException $e) {
                $this->error('❌ Integration Failed (HTTP ' . $e->getStatusCode() . ')');
                $this->warn('--- Request Payload ---');
                $this->line(json_encode($e->getPayload(), JSON_PRETTY_PRINT));
                $this->warn('--- Response Body ---');
                $this->line($this->formatBody($e->getResponseBody()));
                
                $this->line('');
                $this->comment('💡 Check storage/logs/lulu.log for the full raw transaction.');
            }
        } else {
            $this->line('');
            $this->comment('💡 Use --full to test real address validation and job creation.');
        }

        $this->line('--------------------------------------------------');
        $this->info('🏁 Diagnostic Complete.');
        
        return 0;
    }

    protected function formatBody($body): string
    {
        if (empty($body)) return '[Empty Response]';
        
        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($json, JSON_PRETTY_PRINT);
        }
        
        return substr(strip_tags($body), 0, 500);
    }

    protected function renderTroubleshooting(): void
    {
        $this->line('');
        $this->warn('Troubleshooting Tips:');
        $this->line('1. Check .env variables LULU_CLIENT_KEY and LULU_CLIENT_SECRET.');
        $this->line('2. Run php artisan config:clear.');
        $this->line('3. Ensure your Lulu Developer App is set to the correct mode (Sandbox vs Live).');
    }
}
