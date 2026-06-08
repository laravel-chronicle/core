<?php

namespace Chronicle\Anchoring;

/**
 * Tiny DER helper - only what RFC 3161 needs: encode a TimeStampReq and read a
 * single OCTET STRING (the messageImprint) out of a verified TSTInfo. Not a
 * general ASN.1 library.
 */
final class Asn1
{
    public static function sequence(string ...$parts): string
    {
        return self::tlv(0x30, implode('', $parts));
    }

    public static function integer(int $value): string
    {
        $bytes = ltrim(pack('N', $value), "\x00");

        if ($bytes === '') {
            $bytes = "\x00";
        }

        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00".$bytes;
        }

        return self::tlv(0x02, $bytes);
    }

    public static function octetString(string $raw): string
    {
        return self::tlv(0x04, $raw);
    }

    public static function boolean(bool $value): string
    {
        return self::tlv(0x01, $value ? "\xff" : "\x00");
    }

    public static function null(): string
    {
        return "\x05\x00";
    }

    /**
     * Encode an OID from dotted form (e.g. '2.16.840.1.101.3.4.2.1' = sha256).
     */
    public static function oid(string $dotted): string
    {
        $parts = array_map('intval', explode('.', $dotted));
        $body = chr($parts[0] * 40 + $parts[1]);

        foreach (array_slice($parts, 2) as $n) {
            $stack = [$n & 0x7F];
            $n >>= 7;
            while ($n > 0) {
                array_unshift($stack, ($n & 0x7F) | 0x80);
                $n >>= 7;
            }
            $body .= implode('', array_map('chr', $stack));
        }

        return self::tlv(0x06, $body);
    }

    private static function tlv(int $tag, string $content): string
    {
        return chr($tag).self::length(strlen($content)).$content;
    }

    private static function length(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }

        $bytes = ltrim(pack('N', $len), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }
}
