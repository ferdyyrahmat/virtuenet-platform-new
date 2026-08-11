<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description'];

    public static function getByKey(string $key, $default = null)
    {
        self::validateKey($key);
        $setting = Cache::rememberForever("setting.{$key}", fn () => self::where('key', $key)->first());

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true, 512, JSON_THROW_ON_ERROR),
            default => $setting->value,
        };
    }

    public static function setByKey(string $key, $value, string $type = 'string', ?string $description = null)
    {
        self::validateKey($key);

        $storedValue = match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'integer' => self::integerValue($value),
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            'string' => is_scalar($value) ? (string) $value : throw new InvalidArgumentException('String settings must be scalar.'),
            default => throw new InvalidArgumentException('Unsupported system setting type.'),
        };

        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'description' => $description,
            ],
        );

        Cache::forget("setting.{$key}");

        return $setting;
    }

    private static function validateKey(string $key): void
    {
        if (! preg_match('/^[a-z][a-z0-9_.-]{0,99}$/', $key)) {
            throw new InvalidArgumentException('System setting keys must be lowercase and namespaced.');
        }
    }

    private static function integerValue(mixed $value): string
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        if ($integer === null) {
            throw new InvalidArgumentException('Integer settings must contain a valid integer.');
        }

        return (string) $integer;
    }
}
