/**
 * Migrates hardcoded light-theme colors to CSS variables across frontend source files.
 * Safe replacements only — skips gradients, semantic status colors, and theme definition files.
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const SRC = path.join(__dirname, '../src')

const SKIP_FILES = new Set([
  'variables.scss',
  'dark-mode-pages.scss',
  'dark-mode-global.scss',
  'theme.scss',
  'migrate-theme-vars.mjs',
])

const REPLACEMENTS = [
  [/background:\s*#fff\b/gi, 'background: var(--bg-card)'],
  [/background:\s*#ffffff\b/gi, 'background: var(--bg-card)'],
  [/background:\s*white\b/gi, 'background: var(--bg-card)'],
  [/background:\s*#f9fafb\b/gi, 'background: var(--bg-accent)'],
  [/background:\s*#f8fafc\b/gi, 'background: var(--bg-surface-muted)'],
  [/background:\s*#fafbfc\b/gi, 'background: var(--bg-surface-muted)'],
  [/background:\s*#f1f5f9\b/gi, 'background: var(--bg-accent)'],
  [/background:\s*#f5f7fa\b/gi, 'background: var(--bg-page)'],
  [/background:\s*#f5f6fa\b/gi, 'background: var(--bg-page)'],
  [/background:\s*#f0f2f5\b/gi, 'background: var(--bg-page)'],
  [/background:\s*#f0f4f8\b/gi, 'background: var(--bg-page)'],
  [/background:\s*linear-gradient\(135deg,\s*#fff,\s*#fafbfc\)/gi, 'background: var(--bg-card)'],
  [/background:\s*linear-gradient\(135deg,\s*#ffffff,\s*#fafbfc\)/gi, 'background: var(--bg-card)'],
  [/color:\s*#0f172a\b/gi, 'color: var(--text-primary)'],
  [/color:\s*#1a1a2e\b/gi, 'color: var(--text-primary)'],
  [/color:\s*#101828\b/gi, 'color: var(--text-primary)'],
  [/color:\s*#111827\b/gi, 'color: var(--text-primary)'],
  [/color:\s*#1f2937\b/gi, 'color: var(--text-primary)'],
  [/color:\s*#1e293b\b/gi, 'color: var(--text-dark)'],
  [/color:\s*#334155\b/gi, 'color: var(--text-secondary)'],
  [/color:\s*#374151\b/gi, 'color: var(--text-secondary)'],
  [/color:\s*#475569\b/gi, 'color: var(--text-secondary)'],
  [/color:\s*#64748b\b/gi, 'color: var(--text-muted)'],
  [/color:\s*#6b7280\b/gi, 'color: var(--text-muted)'],
  [/color:\s*#94a3b8\b/gi, 'color: var(--text-muted)'],
  [/color:\s*#98a2b3\b/gi, 'color: var(--text-muted)'],
  [/color:\s*#9ca3af\b/gi, 'color: var(--text-muted)'],
  [/color:\s*#667085\b/gi, 'color: var(--text-label)'],
  [/color:\s*#4b5563\b/gi, 'color: var(--text-secondary)'],
  [/color:\s*#333\b/gi, 'color: var(--text-dark)'],
  [/color:\s*#666\b/gi, 'color: var(--text-muted)'],
  [/color:\s*#999\b/gi, 'color: var(--text-muted)'],
  [/fill:\s*#101828\b/gi, 'fill: var(--text-primary)'],
  [/fill:\s*#0f172a\b/gi, 'fill: var(--text-primary)'],
  [/fill:\s*#1e293b\b/gi, 'fill: var(--text-dark)'],
  [/fill:\s*#98a2b3\b/gi, 'fill: var(--text-muted)'],
  [/stroke:\s*#f2f4f7\b/gi, 'stroke: var(--border-light)'],
  [/stroke:\s*#e2e8f0\b/gi, 'stroke: var(--border-color)'],
  [/border:\s*1px solid #e2e8f0\b/gi, 'border: 1px solid var(--border-color)'],
  [/border:\s*1px solid #e8ecf1\b/gi, 'border: 1px solid var(--border-color)'],
  [/border:\s*1px solid #e5e7eb\b/gi, 'border: 1px solid var(--border-color)'],
  [/border:\s*1px solid #dcdde1\b/gi, 'border: 1px solid var(--border-color)'],
  [/border:\s*1px solid #d1d5db\b/gi, 'border: 1px solid var(--border-color)'],
  [/border:\s*1px solid #ddd\b/gi, 'border: 1px solid var(--border-color)'],
  [/border:\s*1px solid #f0f1f3\b/gi, 'border: 1px solid var(--border-light)'],
  [/border:\s*1px solid #f1f5f9\b/gi, 'border: 1px solid var(--border-light)'],
  [/border:\s*1px solid #eef2f6\b/gi, 'border: 1px solid var(--border-light)'],
  [/border:\s*1px solid #e8edf2\b/gi, 'border: 1px solid var(--border-light)'],
  [/border:\s*1px solid #cbd5e1\b/gi, 'border: 1px solid var(--border-strong)'],
  [/border-bottom:\s*1px solid #e2e8f0\b/gi, 'border-bottom: 1px solid var(--border-color)'],
  [/border-bottom:\s*1px solid #e5e7eb\b/gi, 'border-bottom: 1px solid var(--border-color)'],
  [/border-bottom:\s*1px solid #e8edf2\b/gi, 'border-bottom: 1px solid var(--border-color)'],
  [/border-bottom:\s*1px solid #f1f5f9\b/gi, 'border-bottom: 1px solid var(--border-light)'],
  [/border-bottom:\s*1px solid #f3f4f6\b/gi, 'border-bottom: 1px solid var(--border-light)'],
  [/border-bottom:\s*1px solid #f8fafc\b/gi, 'border-bottom: 1px solid var(--border-light)'],
  [/border-bottom:\s*2px solid #e5e7eb\b/gi, 'border-bottom: 2px solid var(--border-color)'],
  [/border-top:\s*1px solid #e5e7eb\b/gi, 'border-top: 1px solid var(--border-color)'],
  [/border-top:\s*1px solid #f0f0f0\b/gi, 'border-top: 1px solid var(--border-light)'],
  [/border-top:\s*1px solid #f1f5f9\b/gi, 'border-top: 1px solid var(--border-light)'],
  [/border-right:\s*1px solid #e2e8f0\b/gi, 'border-right: 1px solid var(--border-color)'],
  [/box-shadow:\s*0 2px 8px rgba\(15,\s*23,\s*42,\s*0\.04\)/gi, 'box-shadow: var(--shadow-sm)'],
  [/box-shadow:\s*0 2px 6px rgba\(15,\s*23,\s*42,\s*0\.04\)/gi, 'box-shadow: var(--shadow-sm)'],
  [/box-shadow:\s*0 2px 12px rgba\(15,\s*23,\s*42,\s*0\.04\)/gi, 'box-shadow: var(--shadow-sm)'],
  [/box-shadow:\s*0 1px 3px rgba\(0,\s*0,\s*0,\s*0\.06\)/gi, 'box-shadow: var(--shadow-sm)'],
  [/box-shadow:\s*0 2px 10px rgba\(0,\s*0,\s*0,\s*0\.1\)/gi, 'box-shadow: var(--shadow-sm)'],
  [/box-shadow:\s*0 2px 10px rgba\(15,\s*23,\s*42,\s*0\.04\)/gi, 'box-shadow: var(--shadow-sm)'],
]

function walk(dir, files = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) {
      if (entry.name === 'node_modules') continue
      walk(full, files)
    } else if (/\.(vue|css|scss)$/.test(entry.name)) {
      files.push(full)
    }
  }
  return files
}

let totalFiles = 0
let totalChanges = 0

for (const file of walk(SRC)) {
  if (SKIP_FILES.has(path.basename(file))) continue

  let content = fs.readFileSync(file, 'utf8')
  let changes = 0
  const original = content

  for (const [pattern, replacement] of REPLACEMENTS) {
    const before = content
    content = content.replace(pattern, replacement)
    if (content !== before) {
      const diff = (before.match(pattern) || []).length
      changes += diff
    }
  }

  if (content !== original) {
    fs.writeFileSync(file, content, 'utf8')
    totalFiles++
    totalChanges += changes
    console.log(`Updated: ${path.relative(SRC, file)} (${changes} replacements)`)
  }
}

console.log(`\nDone. ${totalFiles} files updated, ~${totalChanges} replacements.`)
