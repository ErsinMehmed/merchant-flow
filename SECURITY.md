# Security

Merchant Flow is under development and is not ready for production use or real payment data.

## Reporting a vulnerability

Do not post credentials, personal data, or exploitable details in public issues. When GitHub private vulnerability reporting is enabled, use the repository's Security tab. Otherwise, contact the repository owner privately to arrange a reporting channel.

Include the affected revision, reproduction steps using fictional data, and the expected and actual behavior.

## Credential handling

Keep credentials outside Git and Docker image layers. If a credential is exposed, revoke or rotate it immediately; removing it from the current file does not remove it from history.
