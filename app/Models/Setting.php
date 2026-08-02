<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Laporan transaksi dibatasi per kasir atau tidak.
     * 'all' (default) = semua transaksi toko, tanpa membedakan kasir/shift.
     */
    public static function reportScopedByUser(): bool
    {
        return static::get('report_scope', 'all') === 'user';
    }

    /**
     * Batas rentang tanggal laporan penjualan & pembayaran.
     * 'all' (default) = bebas, 'today' = hanya hari ini.
     */
    public static function reportTodayOnly(): bool
    {
        return static::get('report_date_limit', 'all') === 'today';
    }

    /**
     * Apakah pengguna yang sedang login terkunci ke tanggal hari ini.
     * Admin dikecualikan agar tetap bisa menelusuri data lama.
     */
    public static function reportDateLocked(): bool
    {
        $user = auth()->user();

        return static::reportTodayOnly() && $user && ! $user->isAdmin();
    }

    public static function set(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget("setting_{$key}");
    }
}
