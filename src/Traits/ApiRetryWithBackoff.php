<?php
/*
 *
 *  * Copyright © ${YEAR} SAGE Counseling, Inc.
 *  * All rights reserved.
 *  *
 *  * This file is part SAGE Counseling, Inc. internal software systems.
 *  * Unauthorized use, reproduction, or distribution is strictly prohibited.
 *
 *
 */

/*
   Created 5/16/2025
   Created by Miri Spence (spencema)
   for bi-reflector
*/

namespace Sage\Credible\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

trait ApiRetryWithBackoff
{
    public int $tries = 10;
    public int $timeout = 300;

    protected string $backoffKey;
    protected string $backoffStepKey;

    public function initializeApiRetryWithBackoff(): void
    {
        $this->ensureBackoffKeys();
    }

    protected function ensureBackoffKeys(): void
    {
        if (! isset($this->backoffKey)) {
            $class = static::class;
            $this->backoffKey     = "backoff:api:{$class}";
            $this->backoffStepKey = "backoff:api:{$class}:step";
        }
    }

    public function checkAndApplyBackoff(): int
    {
        $this->ensureBackoffKeys();
        $ttl = Redis::ttl($this->backoffKey);
        return $ttl > 0 ? $ttl : 0;
    }

    public function escalateBackoff(): int
    {
        $this->ensureBackoffKeys();
        $steps = Config::get(
            'importworker.backoff_steps',
            config('credible.api_backoff_timing')
        );

        $step = (int)Redis::get($this->backoffStepKey);

        if (!isset($steps[$step])) {
            $step = count($steps) - 1;
        }

        $seconds = (int)$steps[$step];

        Redis::setex($this->backoffKey, $seconds, 1);
        Redis::set($this->backoffStepKey, $step + 1);

        return $seconds;
    }

    public function clearBackoff(): void
    {
        $this->ensureBackoffKeys();
        Redis::del($this->backoffKey, $this->backoffStepKey);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    public function failed(\Throwable $e): void
    {
        \Log::error("API batch permanently failed", [
            'job' => static::class,
            'params' => [$this->param1, $this->param2],
            'error' => $e->getMessage(),
            'attempts' => $this->job->attempts(),
            'max_tries' => $this->tries,
        ]);
    }

    /**
     * Force a full-halt backoff of $base seconds ± $jitterMax.
     * Resets the step counter so that the next escalateBackoff() starts fresh.
     *
     * @param int $baseSeconds Base backoff duration (default 60s)
     * @param int $jitterMax +/- seconds of random jitter (default ±5s)
     * @return int  The exact seconds that were set
     */
    public function forceHaltBackoff(int $baseSeconds = 60, int $jitterMax = 5): int
    {
        $jitter = $jitterMax > 0 ? random_int(-$jitterMax, $jitterMax) : 0;
        $seconds = max(0, $baseSeconds + $jitter);

        // write directly into your Redis keys
        Redis::setex($this->backoffKey, $seconds, 1);

        // Redis::set($this->backoffStepKey, 0);

        return $seconds;
    }

}
