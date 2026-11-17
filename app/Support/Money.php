<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class Money
{
    public static function format(int|float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0';
        }

        return number_format((int) $amount, 0, '.', ',');
    }

    public static function toman(int|float|string|null $amount): string
    {
        return self::format($amount).' تومان';
    }

    public static function normalize(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_int($value)) {
            return $value >= 0 ? $value : throw new InvalidArgumentException('Invalid monetary value.');
        }

        if (! is_string($value) && ! is_numeric($value)) {
            throw new InvalidArgumentException('Invalid monetary value.');
        }

        $normalized = strtr(trim((string) $value), [
            "\u{06F0}" => '0', "\u{06F1}" => '1', "\u{06F2}" => '2', "\u{06F3}" => '3', "\u{06F4}" => '4',
            "\u{06F5}" => '5', "\u{06F6}" => '6', "\u{06F7}" => '7', "\u{06F8}" => '8', "\u{06F9}" => '9',
            "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3', "\u{0664}" => '4',
            "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7', "\u{0668}" => '8', "\u{0669}" => '9',
        ]);

        if (preg_match('/[,.\x{060C}\x{066C}\x{00A0} ]/u', $normalized) && ! preg_match('/^\d{1,3}(?:[,.\x{060C}\x{066C}\x{00A0} ]\d{3})+$/u', $normalized)) {
            throw new InvalidArgumentException('Invalid monetary value.');
        }

        $digits = preg_replace('/[,.\x{060C}\x{066C}\x{00A0} ]/u', '', $normalized);
        if ($digits === null || ! preg_match('/^\d+$/', $digits)) {
            throw new InvalidArgumentException('Invalid monetary value.');
        }

        $integer = filter_var($digits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($integer === false) {
            throw new InvalidArgumentException('Monetary value is out of range.');
        }

        return $integer;
    }

    /** @param array<int, string> $fields */
    public static function normalizeRequest(Request $request, array $fields): void
    {
        $normalized = [];
        foreach ($fields as $field) {
            try {
                $normalized[$field] = self::normalize($request->input($field));
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([$field => 'مبلغ باید فقط شامل رقم‌های معتبر باشد.']);
            }
        }

        $request->merge($normalized);
    }
}
