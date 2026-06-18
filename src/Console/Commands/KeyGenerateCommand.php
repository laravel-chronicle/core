<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Signing\Ed25519SigningProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SodiumException;

class KeyGenerateCommand extends Command
{
    protected $signature = 'chronicle:key:generate
        {--id= : Key ID to use in the config snippet (defaults to chronicle-key-YYYYMMDD)}';

    protected $description = 'Generate an Ed25519 keypair for use in Chronicle signing.keys';

    /**
     * @throws SodiumException
     */
    public function handle(): int
    {
        $idOption = $this->option('id');
        $keyId = is_string($idOption) && $idOption !== ''
            ? $idOption
            : 'chronicle-key-'.now()->format('Ymd');

        $keypair = sodium_crypto_sign_keypair();
        $privateRaw = sodium_crypto_sign_secretkey($keypair);
        $publicRaw = sodium_crypto_sign_publickey($keypair);

        $privateB64 = base64_encode($privateRaw);
        $publicB64 = base64_encode($publicRaw);

        sodium_memzero($privateRaw);
        sodium_memzero($keypair);

        $envPrivate = 'CHRONICLE_PRIVATE_KEY_'.Str::upper(Str::slug($keyId, '_'));
        $envPublic = 'CHRONICLE_PUBLIC_KEY_'.Str::upper(Str::slug($keyId, '_'));

        $line = str_repeat('─', 60);

        $this->newLine();
        $this->info('Generated Ed25519 keypair (key ID: '.$keyId.')');
        $this->line($line);
        $this->line('Private key (base64):  '.$privateB64);
        $this->line('Public key  (base64):  '.$publicB64);
        $this->newLine();
        $this->line('Ready-to-paste signing.keys entry:');
        $this->line($line);
        $this->line("'$keyId' => [");
        $this->line("    'provider'    => \\".Ed25519SigningProvider::class.'::class,');
        $this->line("    'algorithm'   => 'ed25519',");
        $this->line("    'private_key' => env('$envPrivate'),");
        $this->line("    'public_key'  => env('$envPublic'),");
        $this->line('],');
        $this->newLine();
        $this->warn('SECURITY: Never commit the private key. Store it in a secret manager');
        $this->warn('(AWS Secrets Manager, HashiCorp Vault, 1Password, etc.) and inject');
        $this->warn('via the environment variable '.$envPrivate.'.');

        return self::SUCCESS;
    }
}
