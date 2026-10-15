---
title: "Commit messages are English, imperative, one line"
scope: "pull-request"
path: ["**/*"]
severity_min: "high"
buckets: ["style-conventions"]
enabled: true
---

## Instructions

Review the commits and the pull request title in the changeset.

- English only, imperative mood ("add", "fix", "harden", not "added"/"adding").
- Subject line at most 72 characters, no trailing period, no body paragraphs.
- No AI attribution or filler: "This commit...", "As requested", "Generated with ...",
  "Co-authored-by: <assistant>", emoji in the subject.
- No ticket dumps or multi-line essays in the subject; the PR description is the place for detail.
- Turkish characters in a commit subject mean the message is not English — flag it.

Quote the offending subject and give a corrected one-liner in the review comment.

## Examples

### Bad example
Türkçe karakterli uzun bir konu satırı: "feat: yeni özellik eklendi ve testler yazıldı, ayrıca
dokümantasyon güncellendi, fixes #123"

### Good example
"fix: harden version comparison for missing components"

<!-- @kody-sync -->
