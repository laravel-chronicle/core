# Transactions and Correlation IDs

Chronicle supports grouping entries using correlation IDs.

Example:

Chronicle::transaction()->start();

All entries inside the transaction share the same correlation ID.

This allows grouping events from:

- HTTP requests
- background jobs
- batch processes
