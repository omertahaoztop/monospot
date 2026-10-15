---
title: "Destructive infrastructure commands need a dry run first"
scope: "file"
path: ["**/*.sh", "**/*.yml", "**/*.yaml", "**/*.py", "**/*.go", "**/*.md"]
severity_min: "high"
buckets: ["error-handling", "security"]
enabled: true
---

## Instructions

Flag infrastructure-destroying commands that run without a non-destructive check or a scoped
target. Production discipline: dry run first, verify on one host, then widen.

- `rm -rf`, `zpool destroy`, `zfs destroy`, `pct destroy`, `qm destroy`, `kubectl delete`,
  `vault kv delete`, `mc rb`, `git push --force`, `DROP TABLE`/`TRUNCATE`.
- Any of these applied to a wildcard or a variable host list in one shot.
- Deletion of data or snapshots where a `--dry-run`/`--check`/`-n` variant exists and was not used.
- Hard-coded production targets (VIPs, cluster endpoints) inside scripts that also mutate.

Acceptable: a preceding dry-run or plan step, `ansible-playbook --check`/`--limit=1`, `--dry-run`
flags, or an explicit user confirmation prompt. Ask for the safe variant when it exists.

## Examples

### Bad example
```bash
zpool destroy tank
```
### Good example
```bash
zpool status tank && zfs list -r tank   # verify target before any destroy
```

<!-- @kody-sync -->
