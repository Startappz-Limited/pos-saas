<?php

namespace App\Services\Social;

use App\Enums\SocialPlatform;
use App\Services\Social\Contracts\SocialPoster;
use App\Services\Social\Drivers\FacebookDriver;
use App\Services\Social\Drivers\InstagramDriver;
use App\Services\Social\Drivers\NullSocialDriver;
use InvalidArgumentException;

class SocialPosterManager
{
    /**
     * @var array<string, callable(): SocialPoster>
     */
    protected array $customDrivers = [];

    /**
     * @var array<string, SocialPoster>
     */
    protected array $resolved = [];

    public function for(SocialPlatform|string $platform): SocialPoster
    {
        $key = $platform instanceof SocialPlatform ? $platform->value : $platform;

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        if (isset($this->customDrivers[$key])) {
            return $this->resolved[$key] = ($this->customDrivers[$key])();
        }

        return $this->resolved[$key] = match ($key) {
            SocialPlatform::FACEBOOK->value => new FacebookDriver,
            SocialPlatform::INSTAGRAM->value => new InstagramDriver,
            SocialPlatform::META_ADS->value, SocialPlatform::GOOGLE_ADS->value => throw new InvalidArgumentException(
                "Platform [{$key}] is paid-ads; use the corresponding ads service instead."
            ),
            default => new NullSocialDriver,
        };
    }

    /**
     * @param  callable(): SocialPoster  $factory
     */
    public function extend(string $platform, callable $factory): self
    {
        $this->customDrivers[$platform] = $factory;
        unset($this->resolved[$platform]);

        return $this;
    }
}
