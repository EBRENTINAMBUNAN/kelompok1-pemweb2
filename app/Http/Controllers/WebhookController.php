<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verify GitHub signature
        $secret = 'Admin@12345';
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            Log::warning('Webhook: Missing signature');
            return response()->json(['error' => 'Missing signature'], 403);
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Webhook: Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Get the event type
        $event = $request->header('X-GitHub-Event');
        Log::info('Webhook received: ' . $event);

        // Handle push event - auto deploy
        if ($event === 'push') {
            $branch = $request->input('ref', '');

            // Only deploy on push to main/master branch
            if (str_ends_with($branch, '/main') || str_ends_with($branch, '/master')) {
                $projectPath = '/var/www/kelompok1-pemweb2';
                $output = [];

                exec("cd $projectPath && git pull origin 2>&1", $output);
                exec("cd $projectPath && composer install --no-interaction --no-dev --optimize-autoloader 2>&1", $output);
                exec("cd $projectPath && php artisan config:cache 2>&1", $output);
                exec("cd $projectPath && php artisan route:cache 2>&1", $output);
                exec("cd $projectPath && php artisan view:cache 2>&1", $output);
                exec("cd $projectPath && php artisan migrate --force 2>&1", $output);

                Log::info('Webhook deploy output: ' . implode("\n", $output));

                return response()->json([
                    'message' => 'Deployed successfully',
                    'output' => $output,
                ], 200);
            }

            return response()->json(['message' => 'Ignored: not main/master branch'], 200);
        }

        return response()->json(['message' => 'Webhook received: ' . $event], 200);
    }
}