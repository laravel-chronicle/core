<?php

declare(strict_types=1);

namespace Chronicle\Anchoring;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Contracts\AnchorProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * RFC 3161 trusted-timestamp anchor. anchor() builds a TimeStampReq over the
 * checkpoint digest, POSTs it to the configured TSA, and stores the returned
 * TimeStampResp (base64) as proof. verify() validates that token OFFLINE with
 * `openssl ts -verify` against the configured TSA certificate - this applies the
 * RFC 3161 timestamping cert purpose + CA trust AND confirms the token's
 * messageImprint equals the checkpoint digest in one step. No cloud SDK, no
 * network on verify.
 *
 * Why `openssl ts -verify` and not `openssl_cms_verify`: a TSA signing cert
 * carries `extendedKeyUsage = timeStamping`, which `openssl_cms_verify`'s default
 * S/MIME signing-purpose check rejects; the only override (OPENSSL_CMS_NOVERIFY)
 * skips ALL cert/CA trust, which is unacceptable for an anchor. The `ts` verb
 * applies the correct purpose.
 */
final class Rfc3161TimestampAnchor implements AnchorProvider
{
    protected const SHA256_OID = '2.16.840.1.101.3.4.2.1';

    protected string $tsaUrl;

    protected string $tsaCertificatePath;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $url = $config['tsa_url'] ?? null;
        $cert = $config['tsa_certificate'] ?? null;

        if (! is_string($url) || ! is_string($cert)) {
            throw new RuntimeException('Rfc3161TimestampAnchor requires string tsa_url and tsa_certificate config.');
        }

        $this->tsaUrl = $url;
        $this->tsaCertificatePath = $cert;
    }

    public function name(): string
    {
        return 'rfc3161';
    }

    /**
     * @throws ConnectionException
     */
    public function anchor(Checkpoint $checkpoint): AnchorReceipt
    {
        $digest = hex2bin(CheckpointDigest::for($checkpoint));
        if ($digest === false) {
            throw new RuntimeException('Invalid checkpoint digest.');
        }

        $request = $this->buildRequest($digest);

        $response = Http::withBody($request, 'application/timestamp-query')
            ->post($this->tsaUrl);

        if (! $response->successful()) {
            throw new RuntimeException("TSA request failed with status {$response->status()}.");
        }

        return new AnchorReceipt(
            provider: $this->name(),
            reference: $this->tsaUrl,
            proof: base64_encode($response->body()),
            anchoredAt: now()->toImmutable(),
        );
    }

    public function verify(Checkpoint $checkpoint, AnchorReceipt $receipt): bool
    {
        if ($receipt->proof === null) {
            return false;
        }

        $token = base64_decode($receipt->proof, true);
        if ($token === false) {
            return false;
        }

        $digest = hex2bin(CheckpointDigest::for($checkpoint));
        if ($digest === false) {
            return false;
        }

        $caPem = @file_get_contents($this->tsaCertificatePath);
        if ($caPem === false) {
            return false;
        }

        return $this->verifyTimestampToken($token, $digest, $caPem);
    }

    /**
     * Verify a TimeStampResp (or bare token) offline against $caPem and confirm
     * its messageImprint equals $digest, via `openssl ts -verify`. Isolated so
     * the crypto strategy stays swappable.
     *
     * @param  string  $token  raw TimeStampResp bytes (as returned by the TSA)
     * @param  string  $digest  raw 32-byte checkpoint digest the token must cover
     * @param  string  $caPem  trusted TSA CA certificate(s), PEM
     */
    public function verifyTimestampToken(string $token, string $digest, string $caPem): bool
    {
        $tokenFile = $this->tempFile($token);
        $caFile = $this->tempFile($caPem);

        try {
            // `-digest <hex>` makes `ts -verify` re-check messageImprint == digest;
            // CA trust + the timeStamping cert purpose are applied automatically.
            $process = new Process([
                'openssl', 'ts', '-verify',
                '-digest', bin2hex($digest),
                '-in', $tokenFile,
                '-CAfile', $caFile,
            ]);
            $process->run();

            return $process->isSuccessful();
        } finally {
            @unlink($tokenFile);
            @unlink($caFile);
        }
    }

    protected function buildRequest(string $digest): string
    {
        // TimeStampReq ::= SEQUENCE { version INTEGER(1), messageImprint, certReq BOOLEAN }
        $messageImprint = Asn1::sequence(
            Asn1::sequence(Asn1::oid(self::SHA256_OID), Asn1::null()),
            Asn1::octetString($digest),
        );

        return Asn1::sequence(
            Asn1::integer(1),
            $messageImprint,
            Asn1::boolean(true),
        );
    }

    protected function tempFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'chr-tsa-');
        file_put_contents($path, $contents);

        return $path;
    }
}
