<?php
/**
 * AirOJ — TOTP 2FA (兼容 Google Authenticator / Authy)
 */
class TOTP
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * 生成随机 base32 密钥（16 字节 = 26 字符）
     */
    public static function generateSecret(): string
    {
        $bytes = random_bytes(16);
        return self::base32Encode($bytes);
    }

    /**
     * 获取当前有效的 TOTP 验证码（6 位）
     */
    public function getCode(?int $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = (int)floor(time() / 30);
        }
        $key = self::base32Decode($this->secret);
        $msg = pack('J', $timeSlice); // 64-bit big-endian
        $hash = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * 验证验证码（允许 ±1 步的偏差）
     */
    public function verify(string $code): bool
    {
        $now = (int)floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            if ($this->getCode($now + $i) === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * 生成 otpauth:// URI（用于生成二维码）
     */
    public static function getOTPAuthURI(string $label, string $secret, string $issuer = 'AirOJ'): string
    {
        $label = rawurlencode($label);
        $issuer = rawurlencode($issuer);
        return "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    // ----- base32 编解码 -----
    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $result = '';
        $bits = 0; $value = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $value = ($value << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $result .= $alphabet[($value >> $bits) & 0x1f];
            }
        }
        if ($bits > 0) {
            $result .= $alphabet[($value << (5 - $bits)) & 0x1f];
        }
        return $result;
    }

    private static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $result = '';
        $bits = 0; $value = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $pos = strpos($alphabet, $data[$i]);
            if ($pos === false) continue;
            $value = ($value << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($value >> $bits) & 0xff);
            }
        }
        return $result;
    }
}
