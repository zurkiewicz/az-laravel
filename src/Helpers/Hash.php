<?php
namespace AZ\Laravel\Helpers;

use Illuminate\Support\Facades\Hash as Base;

class Hash extends Base
{


    /**
     * Generate a keyed sha256 hash using the HMAC method (length 64).
     * 
     * @param mixed $value
     * @param string|null $key Default: config('app.key')
     * @param bool $binary
     * @return string
     */
    public static function sha256_hmac($value, ?string $key = null, bool $binary = false): string
    {

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        $value = (string) $value;
        $key ??= config('app.key');

        return hash_hmac('sha256', $value, $key, $binary);

    }


}