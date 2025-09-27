<?php

namespace App\Support;

class TimeFormatter
{
    public static function relativeToNow(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Just now';
        }

        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        }

        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }

        if ($diff < 172800) {
            return 'Yesterday';
        }

        $days = (int) floor($diff / 86400);
        return $days . ' days ago';
    }
}
