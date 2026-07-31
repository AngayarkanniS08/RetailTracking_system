# AGENTS.md — Mandatory Workspace Rules & Security Protocols

This document defines strictly enforced rules and security guidelines for all AI coding agents working in this repository.

---

## 🛡️ MANDATORY SECURITY & CLEAN CODE RULES (P0 PRIORITY)

### 1. 🛑 STRICT PREVENT XSS IN DOM RENDERING (NEVER UNESCAPED HTML)

**Rule:** NEVER interpolate raw variables directly inside template literals assigned to `innerHTML`, `outerHTML`, or DOM strings.

❌ **FORBIDDEN (XSS Vulnerability):**
```javascript
// BAD: Direct interpolation without HTML escaping
tbody.innerHTML = items.map(item => `
  <tr>
    <td>${item.name}</td>
    <td>${item.phone}</td>
  </tr>
`).join('');
```

✅ **MANDATORY PATTERN:**
Always import `escapeHtml` from `../utils/format.js` (or use `BackupService.escapeHtml`) and escape all dynamic string variables before rendering:
```javascript
import { escapeHtml } from '../utils/format.js';

// GOOD: All dynamic text variables escaped safely
tbody.innerHTML = items.map(item => {
  const safeName = escapeHtml(item.name);
  const safePhone = escapeHtml(item.phone);
  return `
    <tr>
      <td style="font-weight:600;">${safeName}</td>
      <td>${safePhone}</td>
    </tr>
  `;
}).join('');
```

---

### 2. 🛑 PROTOTYPE POLLUTION & OBJECT NOTATION SAFETY

**Rule:** Avoid using dynamic user input as plain object keys in `obj[userInput]`.

❌ **FORBIDDEN:**
```javascript
// BAD: Subject to prototype pollution if userInput is '__proto__' or 'constructor'
_cache[userInput] = value;
const item = _cache[userInput];
```

✅ **MANDATORY PATTERN:**
Use a `Map` instance for dynamic key-value lookup:
```javascript
// GOOD: Safe Map data structure
const _cache = new Map();
_cache.set(String(key), value);
const item = _cache.get(String(key));
```

---

### 3. 🛑 BACKEND OUTPUT ENCODING & CACHE SAFETY

**Rule:** In PHP API controllers:
1. Always set strict JSON response headers: `header('Content-Type: application/json; charset=utf-8');`
2. Ensure invalidation of Valkey/Redis cache whenever seeders or schema/data migrations alter underlying user/tenant mappings.

---

### 4. 🧪 REGRESSION VERIFICATION CHECKLIST FOR AGENTS

Before completing any task, every AI agent MUST verify:
- [ ] No unescaped variables in `innerHTML` or template string interpolations.
- [ ] No dynamic object key writes using plain `{}` objects.
- [ ] Modern, safe DOM construction or `escapeHtml()` used across all JS view modules.
