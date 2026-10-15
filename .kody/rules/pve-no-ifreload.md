---
title: "Never run ifreload on Proxmox hosts"
scope: "file"
path: ["**/*.yml", "**/*.yaml", "**/*.sh", "**/*.py", "**/*.md", "**/*.j2"]
severity_min: "critical"
buckets: ["security"]
enabled: true
---

## Instructions

`ifreload -a` (and the Proxmox GUI "Apply Configuration") flushes every interface on a node at
once. On a live cluster this drops the cluster network and can take the node out of quorum.

- Flag `ifreload -a`, `ifreload --all`, `systemctl reload networking` used as a blanket reload,
  and any documentation telling an operator to press "Apply Configuration".
- Flag `ifdown`/`ifup` on a cluster-facing bridge (`vmbr0` carrying corosync) without a
  safeguard.
- Acceptable: per-interface `ifup <iface>` after an explicit `ifreload -a -u -n` dry run,
  changes staged and applied one host at a time, or an out-of-band path that keeps quorum.
- When a playbook must touch networking, require an explicit dry-run step and a single-host
  rollout before any wider run.
- The dry run is `ifreload -a -u -n`: `-n/--no-act` prints what would happen and
  `-u/--use-current-config` makes it read the current interfaces file instead of the saved state.
  Without `-u` a stale state file produces spurious `vmbrX` errors, so a bare `-n` is not a
  reliable check. (`-u` is listed in `ifreload --help`; older man pages omit it.)

Point at the risky line and suggest the per-interface or staged alternative.

## Examples

### Bad example
```yaml
- name: apply network config
  ansible.builtin.command: ifreload -a
```
### Good example
```yaml
- name: dry run first
  ansible.builtin.command: ifreload -a -u -n
  changed_when: false
```

<!-- @kody-sync -->
