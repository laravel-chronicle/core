<?php

namespace Chronicle\Verification;

enum VerificationFailure: string
{
    // Entry-level
    case NotFound = 'not_found';
    case PayloadHashMismatch = 'payload_hash_mismatch';
    case ChainHashMismatch = 'chain_hash_mismatch';
    case ColumnPayloadDivergence = 'column_payload_divergence';

    // Ledger-level (IntegrityVerifier)
    case CheckpointMissing = 'checkpoint_missing';
    case CheckpointSignatureInvalid = 'checkpoint_signature_invalid';
    case UnknownKey = 'unknown_key';

    // Export-level
    case EntriesMissing = 'entries_missing';
    case ManifestMissing = 'manifest_missing';
    case SignatureMissing = 'signature_missing';
    case ManifestUnreadable = 'manifest_unreadable';
    case ManifestInvalidJson = 'manifest_invalid_json';
    case ManifestInvalid = 'manifest_invalid';
    case SignatureUnreadable = 'signature_unreadable';
    case SignatureInvalidJson = 'signature_invalid_json';
    case SignatureInvalidFormat = 'signature_invalid_format';
    case SignatureInvalid = 'signature_invalid';
    case EntriesUnreadable = 'entries_unreadable';
    case EntriesInvalidJson = 'entries_invalid_json';
    case EntriesInvalidFormat = 'entries_invalid_format';
    case ChainInvalid = 'chain_invalid';
    case DatasetHashMismatch = 'dataset_hash_mismatch';
    case EntryCountMismatch = 'entry_count_mismatch';
    case FirstEntryMismatch = 'first_entry_mismatch';
    case LastEntryMismatch = 'last_entry_mismatch';
    case ChainHeadMismatch = 'chain_head_mismatch';
}
