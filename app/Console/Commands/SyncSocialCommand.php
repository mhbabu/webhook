<?php

namespace App\Console\Commands;

use App\Models\Platform;
use App\Services\Adapters\FacebookAdapter;
use App\Services\Adapters\InstagramAdapter;
use App\Services\Adapters\LinkedInAdapter; // optional
use Illuminate\Console\Command; // optional

class SyncSocialCommand extends Command
{
    protected $signature = 'social:sync {platform?} {accountId?}';

    protected $description = 'Sync social posts/comments/reactions';

    public function handle()
    {
        $platformArg = $this->argument('platform');
        $accountArg = $this->argument('accountId');

        $platforms = $platformArg ? Platform::where('name', $platformArg)->get() : Platform::all();

        foreach ($platforms as $platform) {
            $this->info("Syncing platform: {$platform->name}");
            foreach ($platform->accounts as $account) {
                if ($accountArg && $accountArg != $account->id) {
                    continue;
                }

                $this->info(" - account: {$account->id} ({$account->platform_account_id})");
                if ($platform->name === 'facebook') {
                    $this->info(" - Syncing Facebook account ID: {$account->platform_account_id}");
                    app(FacebookAdapter::class)->syncPosts($platform, $account);
                } elseif ($platform->name === 'instagram') {
                    app(InstagramAdapter::class)->syncMedia($platform, $account);
                } elseif ($platform->name === 'linkedin') {
                    // app(LinkedInAdapter::class)->syncPosts($platform, $account);
                }
            }
        }
        $this->info('Sync finished.');
    }
}
