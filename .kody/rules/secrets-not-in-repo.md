---
title: "No secrets in repository files"
scope: "file"
path: ["**/*"]
severity_min: "critical"
buckets: ["security"]
enabled: true
---

## Instructions

Flag any credential material committed to the repository. Secrets belong in environment
variables or a secret manager (Vault/OpenBao) and are referenced by path, never pasted.

- Passwords, API tokens, private keys, connection strings with credentials, session secrets.
- Ansible `vars:` blocks, templates (`*.j2`), CI files, compose files, `.env` files.
- Values that look generated but are still secrets: `openssl rand` output, bcrypt hashes of
  real passwords, `accessToken`/`refreshToken`/`deviceToken` fields.
- Also flag secrets written to logs, `debug` output, or command lines (`curl -H "Authorization: ..."`).
- Placeholders (`CHANGE_ME`, `${VAULT_PATH}`, `{{ lookup('community.hashi_vault', ...) }}`) are fine.

Report the file and line, and name the safe alternative: environment variable, Vault path, or
Ansible lookup.

## Examples

### Bad example
```yaml
vars:
  db_password: "example-password-not-real"
  valkey_requirepass: "example-requirepass"
```
### Good example
```yaml
vars:
  db_password: "{{ lookup('community.hashi_vault', 'secret=kv/data/pg:password') }}"
```

<!-- @kody-sync -->
