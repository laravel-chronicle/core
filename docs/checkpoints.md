# Checkpoints

Checkpoints anchor the ledger state.

A checkpoint stores:

- last entry ID
- last chain hash
- entry count
- digital signature

---

# Why Checkpoints Exist

Without checkpoints, an attacker could theoretically rewrite the entire chain.

Checkpoints prevent this by creating externally verifiable anchors.

---

# Creating a Checkpoint

php artisan chronicle:checkpoint

---

# Verifying Checkpoints

php artisan chronicle:verify
