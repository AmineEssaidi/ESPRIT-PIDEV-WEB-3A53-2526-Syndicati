<?php

namespace App\Service\Face;

class FaceEncryptionService
{
    private const ALGORITHM = 'aes-256-gcm';
    private const ITERATIONS = 10000;
    private const KEY_LENGTH = 32;

    /**
     * Derive a 32-byte key from a PIN and a salt.
     */
    public function deriveKey(string $pin, string $userEmail): string
    {
        // Using email as a static salt for PBKDF2
        return hash_pbkdf2('sha256', $pin, $userEmail, self::ITERATIONS, self::KEY_LENGTH, true);
    }

    /**
     * Encrypt data using AES-256-GCM.
     * Returns binary data: IV (12 bytes) | TAG (16 bytes) | Ciphertext
     */
    public function encrypt(string $data, string $key): string
    {
        $iv = openssl_random_pseudo_bytes(12);
        $ciphertext = openssl_encrypt($data, self::ALGORITHM, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $iv . $tag . $ciphertext;
    }

    /**
     * Decrypt data using AES-256-GCM.
     */
    public function decrypt(string $packedData, string $key): ?string
    {
        $iv = substr($packedData, 0, 12);
        $tag = substr($packedData, 12, 16);
        $ciphertext = substr($packedData, 28);

        $plaintext = openssl_decrypt($ciphertext, self::ALGORITHM, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext !== false ? $plaintext : null;
    }

    /**
     * Calculate Euclidean distance between two vectors.
     */
    public function calculateDistance(array $v1, array $v2): float
    {
        $sum = 0;
        foreach ($v1 as $i => $val) {
            $sum += pow($val - $v2[$i], 2);
        }
        return sqrt($sum);
    }
}
