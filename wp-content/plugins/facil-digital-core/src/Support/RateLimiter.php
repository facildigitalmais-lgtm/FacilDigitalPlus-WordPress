<?php

declare(strict_types=1);

namespace FacilDigital\Core\Support;

final class RateLimiter
{
    public function hit(
        string $scope,
        int $userId,
        int $limit,
        int $windowSeconds
    ): bool {
        if ($userId <= 0 || $limit <= 0 || $windowSeconds <= 0) {
            return false;
        }

        $scope = sanitize_key($scope);
        $bucket = (int) floor(time() / $windowSeconds);
        $key = sprintf(
            'fd_rate_%s_%d_%d',
            $scope,
            $userId,
            $bucket
        );

        $count = (int) get_transient($key);
        $count++;

        set_transient(
            $key,
            $count,
            $windowSeconds + 5
        );

        return $count <= $limit;
    }
}
