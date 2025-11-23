<?php

namespace App\Services\SocialSync;

class InstagramService
{
    public function syncInstagram()
    {
        $platform = Platform::where('name', 'instagram')->first();
        $accounts = $platform->accounts;

        $service = new SocialSyncService;

        foreach ($accounts as $account) {
            $igPosts = $this->instagramApi->getPosts($account);

            foreach ($igPosts as $post) {
                $service->syncPost($platform, $account, $post);
            }
        }
    }
}
