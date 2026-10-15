---
title: "Turkish text uses proper Turkish characters"
scope: "file"
path: ["**/*.md", "**/*.py", "**/*.go", "**/*.php", "**/*.ts", "**/*.sh", "**/*.j2", "**/*.txt"]
severity_min: "medium"
buckets: ["style-conventions"]
enabled: true
---

## Instructions

Turkish text (comments, documentation, user-facing strings, commit-adjacent files) must be
written with real Turkish characters: ç, ğ, ı, İ, ö, ş, ü. ASCII-folded Turkish is a defect,
not a preference — `cagrilar` must be `çağrılar`, `duzeltme` must be `düzeltme`.

- Flag words that are obviously Turkish with letters stripped: `cagri`, `duzeltme`, `kullanici`,
  `baglanti`, `guvenlik`, `sifre`, `yonetici`, `ozellik`, `degisken`, `surum`, `gecersiz`.
- Case conversion must follow Turkish rules: `i` → `İ`, `ı` → `I` (`INCELEME`, `İZLEME`).
- English identifiers and code symbols stay ASCII — only prose is affected.
- Files must be UTF-8; flag a BOM if one is added.

Report the ASCII-folded word and its correct Turkish spelling. Do not flag English text.

## Examples

### Bad example
```go
// kullanici listesini doner, hata olursa bos dilim
```
### Good example
```go
// kullanıcı listesini döner, hata olursa boş dilim
```

<!-- @kody-sync -->
