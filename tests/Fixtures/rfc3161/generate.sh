#!/usr/bin/env bash
# Generates an offline RFC 3161 fixture: a TSA CA + cert, a timestamp token over
# a FIXED 32-byte digest, and the CA file. Re-run only to refresh the fixture.
set -euo pipefail
cd "$(dirname "$0")"

# 1. Fixed digest the token will cover (32 bytes: 0x00..0x1f).
python3 -c 'import sys;sys.stdout.buffer.write(bytes(range(32)))' > digest.bin

# 2. TSA CA + timestamping cert.
openssl req -x509 -newkey rsa:2048 -keyout tsa-ca.key -out tsa-ca.pem -days 3650 -nodes -subj "/CN=Chronicle Test TSA CA"
openssl req -newkey rsa:2048 -keyout tsa.key -out tsa.csr -nodes -subj "/CN=Chronicle Test TSA"
cat > tsa-ext.cnf <<'EOF'
[ tsa_ext ]
extendedKeyUsage = critical, timeStamping
EOF
openssl x509 -req -in tsa.csr -CA tsa-ca.pem -CAkey tsa-ca.key -CAcreateserial -out tsa.pem -days 3650 -extfile tsa-ext.cnf -extensions tsa_ext

# 3. TSA config + request over the fixed digest, then reply (the token).
cat > tsa.cnf <<'EOF'
# Settings live directly in [tsa] because `openssl ts -reply -section tsa`
# reads the named section itself (it does NOT follow default_tsa).
[ tsa ]
serial = tsa.serial
crypto_device = builtin
signer_cert = tsa.pem
certs = tsa-ca.pem
signer_key = tsa.key
signer_digest = sha256
default_policy = 1.2.3.4.1
digests = sha256
accuracy = secs:1
clock_precision_digits = 0
ordering = yes
tsa_name = yes
EOF
echo 01 > tsa.serial
DIGEST_HEX=$(xxd -p -c 256 digest.bin)
openssl ts -query -digest "$DIGEST_HEX" -sha256 -cert -out request.tsq
openssl ts -reply -config tsa.cnf -section tsa -queryfile request.tsq -out token.tsr

# 4. Keep only what the test needs.
rm -f tsa-ca.key tsa.key tsa.csr tsa.pem tsa-ext.cnf tsa.cnf tsa.serial tsa.serial.old request.tsq tsa-ca.srl
echo "Fixture generated: token.tsr, tsa-ca.pem, digest.bin"
