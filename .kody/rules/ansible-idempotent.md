---
title: "Ansible playbooks must be idempotent and non-destructive"
scope: "file"
path: ["**/*.yml", "**/*.yaml"]
severity_min: "high"
buckets: ["error-handling", "style-conventions"]
enabled: true
---

## Instructions

A second run of a playbook must report `changed=0` unless something really changed. Flag:

- `ansible.builtin.shell` where `command` or a module would do (no pipes/redirects needed).
- Unconditional restarts/reloads (`service: state=restarted`, `command: systemctl restart ...`)
  that run on every invocation instead of being driven by a handler or a registered `changed` value.
- `changed_when: true` on tasks that do not change anything; missing `changed_when` on
  read-only `command`/`shell` tasks.
- `ignore_errors: true` on tasks whose failure must stop the run.
- Destructive globs (`rm -f /var/log/**/*.log`, `find ... -delete`) that are not scoped to a
  named path and age.
- `run_once: true` on multi-node tasks where every node needs the result, and write-then-delete
  patterns that oscillate between runs.

Suggest the idempotent form (module, handler, `creates:`/`changed_when`, conditional) rather
than just naming the problem.

## Examples

### Bad example
```yaml
- name: restart nginx
  ansible.builtin.shell: systemctl restart nginx
```
### Good example
```yaml
- name: reload nginx
  ansible.builtin.service:
    name: nginx
    state: reloaded
  when: nginx_conf.changed
```

<!-- @kody-sync -->
